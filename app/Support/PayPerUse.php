<?php

namespace App\Support;

use App\Models\Tenant;
use App\Models\TenantUsageEntry;
use Illuminate\Support\Facades\DB;
use M35\HubSitebuilder\Models\GeneratedSite;

/**
 * «Paghi quello che pubblichi»: niente abbonamento.
 * - Annunci gratis.
 * - Promo, prodotto, servizio: 1 € l'uno; nello stesso mese dal 6° 0,80 €, dal 21° 0,50 €.
 * - Chi ha fatto il sito ha i primi N di ogni tipo inclusi.
 * - Si paga una volta per oggetto (ripubblicare o modificare non costa).
 * - Si paga col credito: 15 € in regalo la prima volta, poi ricariche da 5 € in su (carta, bonifico, contanti).
 * - La carta salvata (addebito dopo un mese) è spenta di base (card_enabled): si paga solo con le ricariche.
 * - Credito dai giochi di piramide35.com (buoni M35G): spendibile fino a game_free_cents; il resto si sblocca
 *   con la prima ricarica vera da topup_min_cents in su (vedi gameLockedCents()).
 * - Finito il credito c'è un piccolo fido: il lavoro non si ferma e si salda con la ricarica.
 * - A ogni acquisto e ricarica: ricevuta (non fiscale) per email al cliente e a Max.
 * Chi ha ancora un abbonamento attivo e lo staff non pagano.
 */
class PayPerUse
{
    public const KINDS = ['promo' => 'promo', 'product' => 'prodotto', 'service' => 'servizio'];

    /** Il sito con l'IA: prezzo fisso a parte (non entra nei listini né negli scaglioni del mese). */
    public const SITE = 'site';

    public static function enabled(): bool
    {
        return (bool) config('services.hub_billing.pay_per_use.enabled', true);
    }

    /** Non paga: sistema spento, abbonamento attivo (vecchi clienti) o staff di Hub Core. */
    public static function exempt(Tenant $tenant): bool
    {
        return ! self::enabled()
            || $tenant->hasActiveSubscription()
            || (bool) auth()->user()?->isSuperAdmin();
    }

    /** Si può ancora scegliere la carta salvata (paghi dopo)? Spento di base: solo ricariche. */
    public static function cardEnabled(): bool
    {
        return (bool) config('services.hub_billing.pay_per_use.card_enabled', false);
    }

    /** Carta salvata e ultimo addebito andato a buon fine (sempre false se la carta è spenta). */
    public static function hasCard(Tenant $tenant): bool
    {
        if (! self::cardEnabled()) {
            return false;
        }

        $card = $tenant->settings['ppu_card'] ?? null;

        return is_array($card) && ! empty($card['payment_method']) && empty($card['failed']) && (bool) $tenant->stripe_customer_id;
    }

    /** @param  array{payment_method: string, brand?: ?string, last4?: ?string}  $card */
    public static function saveCard(Tenant $tenant, string $customerId, array $card): void
    {
        $settings = $tenant->settings ?? [];
        $settings['ppu_card'] = ['payment_method' => $card['payment_method'], 'brand' => $card['brand'] ?? null, 'last4' => $card['last4'] ?? null, 'saved_at' => now()->toIso8601String()];

        $tenant->forceFill(['stripe_customer_id' => $customerId, 'settings' => $settings])->save();

        // nuova carta dopo un addebito non riuscito: si riprova al prossimo giro
        TenantUsageEntry::where('tenant_id', $tenant->id)->where('status', 'failed')->update(['status' => 'pending', 'bill_after' => now()]);
    }

    public static function markCardFailed(Tenant $tenant): void
    {
        $settings = $tenant->settings ?? [];
        if (isset($settings['ppu_card']) && is_array($settings['ppu_card'])) {
            $settings['ppu_card']['failed'] = now()->toIso8601String();
            $tenant->forceFill(['settings' => $settings])->save();
        }
    }

    public static function hasSite(Tenant $tenant): bool
    {
        return class_exists(GeneratedSite::class)
            && GeneratedSite::query()->where('tenant_id', $tenant->id)->whereIn('status', ['ready', 'published'])->exists();
    }

    public static function includedLeft(Tenant $tenant, string $kind): int
    {
        if (! self::hasSite($tenant)) {
            return 0;
        }

        $used = TenantUsageEntry::where('tenant_id', $tenant->id)->where('reason', 'included')->where('item_type', $kind)->count();

        return max(0, (int) config('services.hub_billing.pay_per_use.included_with_site', 3) - $used);
    }

    /** Prezzo della prossima pubblicazione di questo tipo, in centesimi (0 = inclusa). */
    public static function priceCents(Tenant $tenant, string $kind): int
    {
        // Premium attivo: tutto illimitato.
        if (self::premiumActive($tenant)) {
            return 0;
        }

        if ($kind === self::SITE || str_starts_with($kind, 'lang_') || str_starts_with($kind, 'addon_')) {
            return max(0, (int) config('services.hub_billing.pay_per_use.site_cents', 100));
        }

        // Più lingue del sito in attesa della carta: 1 € ciascuna.
        if ($kind === 'site_langs') {
            $wanted = class_exists(GeneratedSite::class) ? \M35\HubSitebuilder\Services\SiteTranslations::wanted($tenant->generatedSite) : [];

            return count($wanted) * self::priceCents($tenant, 'lang_x');
        }

        if (self::includedLeft($tenant, $kind) > 0) {
            return 0;
        }

        $nth = TenantUsageEntry::where('tenant_id', $tenant->id)->where('reason', 'use')->whereIn('item_type', array_keys(self::KINDS))
            ->where('created_at', '>=', now()->startOfMonth())->count() + 1;

        $price = 100;
        foreach ((array) config('services.hub_billing.pay_per_use.steps', [1 => 100]) as $from => $cents) {
            if ($nth >= (int) $from) {
                $price = (int) $cents;
            }
        }

        // Appena uscito da Premium: prezzo di favore per un po', poi il normale.
        if (self::premiumExitActive($tenant)) {
            $price = min($price, max(0, (int) config('services.hub_billing.pay_per_use.premium.exit_cents', 80)));
        }

        return $price;
    }

    public static function alreadyPaid(Tenant $tenant, string $kind, int $itemId): bool
    {
        return TenantUsageEntry::where('tenant_id', $tenant->id)->where('item_type', $kind)->where('item_id', $itemId)->exists();
    }

    /**
     * Registra la pubblicazione: gratis se inclusa, altrimenti da addebitare tra un mese sulla carta salvata.
     * false = manca la carta (non cambia niente: si chiede la carta e poi si pubblica).
     */
    public static function charge(Tenant $tenant, string $kind, int $itemId, string $title): bool
    {
        if (self::exempt($tenant) || self::alreadyPaid($tenant, $kind, $itemId)) {
            return true;
        }

        return DB::transaction(function () use ($tenant, $kind, $itemId, $title) {
            // una pubblicazione alla volta per attività: prezzi a scalare e inclusi restano giusti
            Tenant::whereKey($tenant->id)->lockForUpdate()->first();

            if (self::alreadyPaid($tenant, $kind, $itemId)) {
                return true;
            }

            $tenant = $tenant->fresh();
            $price = self::priceCents($tenant, $kind);
            $credit = self::spendableCents($tenant);
            $wasPositive = $credit >= 0;

            // Come si paga: 1) il credito (15 € in regalo la prima volta, poi le ricariche);
            // 2) la carta salvata, addebito dopo un mese; 3) il fido, per non fermare il lavoro (si salda con una ricarica).
            $status = match (true) {
                $price === 0 => 'free',
                $credit >= $price => 'credit',
                self::hasCard($tenant) => 'pending',
                $credit - $price >= -self::overdraftCents() => 'owed',
                default => null,
            };

            if ($status === null) {
                return false;
            }

            $entry = TenantUsageEntry::create([
                'tenant_id' => $tenant->id,
                'amount_cents' => $price,
                'reason' => $price > 0 ? 'use' : (self::premiumActive($tenant) ? 'premium' : 'included'),
                'status' => $status,
                'item_type' => $kind,
                'item_id' => $itemId,
                'bill_after' => $status === 'pending' ? now()->addDays((int) config('services.hub_billing.pay_per_use.bill_after_days', 30)) : null,
                'description' => ucfirst(self::label($kind)).' «'.mb_strimwidth($title, 0, 120, '…').'»',
                'user_id' => auth()->id(),
            ]);

            if (in_array($status, ['credit', 'owed'], true)) {
                self::moveCredit($tenant, -$price);
            }

            if ($price > 0) {
                DB::afterCommit(fn () => CreditNotices::receipt($tenant->fresh(), $entry));
            }
            if ($status === 'owed' && $wasPositive) {
                DB::afterCommit(fn () => CreditNotices::overdraft($tenant->fresh()));
            }

            return true;
        });
    }

    /* ---------- Premium: un tot al mese dal credito, tutto illimitato ---------- */

    public static function premiumEnabled(): bool
    {
        return self::enabled() && (bool) config('services.hub_billing.pay_per_use.premium.enabled', true);
    }

    public static function premiumCents(): int
    {
        return max(0, (int) config('services.hub_billing.pay_per_use.premium.monthly_cents', 1900));
    }

    /** @return array{until?: string, renew?: bool, started_at?: string, ended_at?: string} */
    public static function premium(Tenant $tenant): array
    {
        return (array) ($tenant->settings['ppu_premium'] ?? []);
    }

    public static function premiumActive(Tenant $tenant): bool
    {
        $until = self::premium($tenant)['until'] ?? null;

        return $until !== null && now()->lt(\Illuminate\Support\Carbon::parse($until));
    }

    /** Uscito da Premium da poco: pubblicazioni al prezzo di favore (exit_cents) per exit_days giorni. */
    public static function premiumExitActive(Tenant $tenant): bool
    {
        $p = self::premium($tenant);
        if (self::premiumActive($tenant) || empty($p['until'])) {
            return false;
        }

        $days = max(0, (int) config('services.hub_billing.pay_per_use.premium.exit_days', 30));

        return now()->lt(\Illuminate\Support\Carbon::parse($p['until'])->addDays($days));
    }

    /**
     * Attiva (o rinnova) Premium scalando il mese dal credito spendibile. false = credito non basta.
     * Se è già attivo, il nuovo mese si aggiunge in coda.
     */
    public static function startPremium(Tenant $tenant, bool $renewal = false): bool
    {
        if (! self::premiumEnabled()) {
            return false;
        }

        $entry = DB::transaction(function () use ($tenant, $renewal) {
            Tenant::whereKey($tenant->id)->lockForUpdate()->first();
            $tenant = $tenant->fresh();
            $price = self::premiumCents();

            if (self::spendableCents($tenant) < $price) {
                return null;
            }

            self::moveCredit($tenant, -$price);
            $tenant = $tenant->fresh();
            $p = self::premium($tenant);
            $from = self::premiumActive($tenant) ? \Illuminate\Support\Carbon::parse($p['until']) : now();
            $until = $from->copy()->addDays(max(1, (int) config('services.hub_billing.pay_per_use.premium.days', 30)));

            $settings = $tenant->settings ?? [];
            $settings['ppu_premium'] = [
                'until' => $until->toIso8601String(),
                'renew' => true,
                'started_at' => $p['started_at'] ?? now()->toIso8601String(),
            ];
            $tenant->forceFill(['settings' => $settings])->save();

            return TenantUsageEntry::create([
                'tenant_id' => $tenant->id,
                'amount_cents' => $price,
                'reason' => 'premium_fee',
                'status' => 'credit',
                'description' => ($renewal ? 'Rinnovo' : 'Attivazione').' Premium fino al '.$until->timezone(config('app.timezone'))->format('d/m/Y'),
                'user_id' => auth()->id(),
            ]);
        });

        if ($entry) {
            CreditNotices::receipt($tenant->fresh(), $entry);
        }

        return (bool) $entry;
    }

    /** Il cliente non vuole rinnovare: Premium resta fino alla scadenza, poi prezzi di favore e infine normali. */
    public static function stopPremiumRenewal(Tenant $tenant): void
    {
        $settings = $tenant->settings ?? [];
        if (isset($settings['ppu_premium'])) {
            $settings['ppu_premium']['renew'] = false;
            $tenant->forceFill(['settings' => $settings])->save();
        }
    }

    public static function resumePremiumRenewal(Tenant $tenant): void
    {
        $settings = $tenant->settings ?? [];
        if (self::premiumActive($tenant)) {
            $settings['ppu_premium']['renew'] = true;
            unset($settings['ppu_premium']['ended_at']);
            $tenant->forceFill(['settings' => $settings])->save();
        }
    }

    /* ---------- Credito prepagato ---------- */

    /** Credito regalato a ogni attività la prima volta (lo finanzia Hub Core). */
    public static function welcomeCents(): int
    {
        return max(0, (int) config('services.hub_billing.pay_per_use.welcome_credit_cents', 1500));
    }

    /** Fido: quanto si può andare sotto zero senza carta, così il lavoro non si ferma (si salda con la ricarica). */
    public static function overdraftCents(): int
    {
        return max(0, (int) config('services.hub_billing.pay_per_use.overdraft_cents', 500));
    }

    /** Ricarica minima. */
    public static function topUpMinCents(): int
    {
        return max(100, (int) config('services.hub_billing.pay_per_use.topup_min_cents', 500));
    }

    /** Credito disponibile (può essere negativo se si usa il fido). Alla prima lettura arriva il regalo di benvenuto. */
    public static function creditCents(Tenant $tenant): int
    {
        self::grantWelcome($tenant);

        return (int) ($tenant->settings['ppu_credit']['cents'] ?? 0);
    }

    /** Credito che si può spendere adesso: il credito meno la parte dei giochi ancora bloccata (può essere negativo col fido). */
    public static function spendableCents(Tenant $tenant): int
    {
        return self::creditCents($tenant) - self::gameLockedCents($tenant);
    }

    /** Quanto si può spendere del credito vinto ai giochi prima di una ricarica vera. */
    public static function gameFreeCents(): int
    {
        return max(0, (int) config('services.hub_billing.pay_per_use.game_free_cents', 300));
    }

    /**
     * Parte del credito dei giochi ancora bloccata: tutto quello vinto oltre gameFreeCents(),
     * finché l'attività non ha fatto almeno una ricarica vera da topUpMinCents() in su (carta, bonifico, contanti; non i regali).
     */
    public static function gameLockedCents(Tenant $tenant): int
    {
        $won = (int) TenantUsageEntry::where('tenant_id', $tenant->id)->where('reason', 'voucher')->sum('amount_cents');
        if ($won <= self::gameFreeCents() || self::hasRealTopUp($tenant)) {
            return 0;
        }

        return $won - self::gameFreeCents();
    }

    /** Ha mai fatto una ricarica vera di almeno topUpMinCents()? (i «regali» segnati da Max non contano) */
    public static function hasRealTopUp(Tenant $tenant): bool
    {
        return TenantUsageEntry::where('tenant_id', $tenant->id)->where('reason', 'topup')
            ->where('amount_cents', '>=', self::topUpMinCents())
            ->where('description', 'not like', '%(regalo)%')
            ->exists();
    }

    public static function grantWelcome(Tenant $tenant): void
    {
        if (! empty($tenant->settings['ppu_credit']['welcome_at']) || self::welcomeCents() === 0 || ! self::enabled()) {
            return;
        }

        $settings = $tenant->settings ?? [];
        $settings['ppu_credit'] = [
            'cents' => (int) ($settings['ppu_credit']['cents'] ?? 0) + self::welcomeCents(),
            'welcome_at' => now()->toIso8601String(),
        ];
        $tenant->forceFill(['settings' => $settings])->save();

        TenantUsageEntry::create([
            'tenant_id' => $tenant->id,
            'amount_cents' => self::welcomeCents(),
            'reason' => 'gift',
            'status' => 'topup',
            'description' => 'Credito di benvenuto offerto da Hub Core',
        ]);
    }

    /** Ricarica (carta, bonifico o contanti segnati da Max): prima salda il fido, poi resta come credito. */
    public static function topUp(Tenant $tenant, int $cents, string $method, ?string $reference = null): ?TenantUsageEntry
    {
        if ($cents <= 0) {
            return null;
        }

        $entry = DB::transaction(function () use ($tenant, $cents, $method, $reference) {
            Tenant::whereKey($tenant->id)->lockForUpdate()->first();

            if ($reference && TenantUsageEntry::where('tenant_id', $tenant->id)->where('reason', 'topup')->where('payment_intent_id', $reference)->exists()) {
                return null; // la stessa ricarica arriva due volte (ritorno + webhook)
            }

            $tenant = $tenant->fresh();
            self::grantWelcome($tenant);
            self::moveCredit($tenant, $cents);

            if (self::creditCents($tenant->fresh()) >= 0) {
                TenantUsageEntry::where('tenant_id', $tenant->id)->where('status', 'owed')->update(['status' => 'credit']);
            }

            return TenantUsageEntry::create([
                'tenant_id' => $tenant->id,
                'amount_cents' => $cents,
                'reason' => 'topup',
                'status' => 'topup',
                'payment_intent_id' => $reference,
                'billed_at' => now(),
                'description' => 'Ricarica del credito ('.$method.')',
                'user_id' => auth()->id(),
            ]);
        });

        if ($entry) {
            CreditNotices::receipt($tenant->fresh(), $entry);
        }

        return $entry;
    }

    /** Buono vinto ai giochi di piramide35.com (GameVoucher): credito regalato, prima salda l'eventuale fido. */
    public static function addVoucher(Tenant $tenant, int $cents, string $code): TenantUsageEntry
    {
        return DB::transaction(function () use ($tenant, $cents, $code) {
            Tenant::whereKey($tenant->id)->lockForUpdate()->first();

            $tenant = $tenant->fresh();
            self::grantWelcome($tenant);
            self::moveCredit($tenant, $cents);

            if (self::creditCents($tenant->fresh()) >= 0) {
                TenantUsageEntry::where('tenant_id', $tenant->id)->where('status', 'owed')->update(['status' => 'credit']);
            }

            return TenantUsageEntry::create([
                'tenant_id' => $tenant->id,
                'amount_cents' => $cents,
                'reason' => 'voucher',
                'status' => 'topup',
                'payment_intent_id' => $code,
                'billed_at' => now(),
                'description' => 'Buono gioco piramide35',
                'user_id' => auth()->id(),
            ]);
        });
    }

    private static function moveCredit(Tenant $tenant, int $cents): void
    {
        $settings = $tenant->fresh()->settings ?? [];
        $settings['ppu_credit']['cents'] = (int) ($settings['ppu_credit']['cents'] ?? 0) + $cents;
        $tenant->forceFill(['settings' => $settings])->save();
    }

    /** Si può pubblicare qualcosa che costa $cents adesso? (credito, fido o carta salvata) */
    public static function canPay(Tenant $tenant, int $cents = 100): bool
    {
        return self::exempt($tenant)
            || self::premiumActive($tenant)
            || self::hasCard($tenant)
            || self::spendableCents($tenant) - $cents >= -self::overdraftCents();
    }

    public static function label(string $kind): string
    {
        if (str_starts_with($kind, 'lang_')) {
            return 'traduzione del sito';
        }
        if (str_starts_with($kind, 'addon_')) {
            return 'opzione del sito';
        }

        return $kind === self::SITE ? 'sito web' : (self::KINDS[$kind] ?? $kind);
    }

    /** Totale ancora da addebitare sulla carta (pubblicazioni dell'ultimo mese). */
    public static function pendingCents(Tenant $tenant): int
    {
        return (int) TenantUsageEntry::where('tenant_id', $tenant->id)->whereIn('status', ['pending', 'failed'])->sum('amount_cents');
    }

    public static function euro(int $cents): string
    {
        return number_format($cents / 100, $cents % 100 === 0 ? 0 : 2, ',', '.').' €';
    }
}
