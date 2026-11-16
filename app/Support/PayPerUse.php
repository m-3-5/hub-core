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
 * - Serve una carta salvata: si pubblica subito e l'addebito arriva dopo un mese, tutto insieme.
 * Chi ha ancora un abbonamento attivo e lo staff non pagano.
 */
class PayPerUse
{
    public const KINDS = ['promo' => 'promo', 'product' => 'prodotto', 'service' => 'servizio'];

    public static function enabled(): bool
    {
        return (bool) config('services.hub_billing.pay_per_use.enabled', false);
    }

    /** Non paga: sistema spento, abbonamento attivo (vecchi clienti) o staff di Hub Core. */
    public static function exempt(Tenant $tenant): bool
    {
        return ! self::enabled()
            || $tenant->hasActiveSubscription()
            || (bool) auth()->user()?->isSuperAdmin();
    }

    /** Carta salvata e ultimo addebito andato a buon fine. */
    public static function hasCard(Tenant $tenant): bool
    {
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
        if (self::includedLeft($tenant, $kind) > 0) {
            return 0;
        }

        $nth = TenantUsageEntry::where('tenant_id', $tenant->id)->where('reason', 'use')
            ->where('created_at', '>=', now()->startOfMonth())->count() + 1;

        $price = 100;
        foreach ((array) config('services.hub_billing.pay_per_use.steps', [1 => 100]) as $from => $cents) {
            if ($nth >= (int) $from) {
                $price = (int) $cents;
            }
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

            $price = self::priceCents($tenant, $kind);
            if ($price > 0 && ! self::hasCard($tenant->fresh())) {
                return false;
            }

            TenantUsageEntry::create([
                'tenant_id' => $tenant->id,
                'amount_cents' => $price,
                'reason' => $price > 0 ? 'use' : 'included',
                'status' => $price > 0 ? 'pending' : 'free',
                'item_type' => $kind,
                'item_id' => $itemId,
                'bill_after' => $price > 0 ? now()->addDays((int) config('services.hub_billing.pay_per_use.bill_after_days', 30)) : null,
                'description' => ucfirst(self::KINDS[$kind] ?? $kind).' «'.mb_strimwidth($title, 0, 120, '…').'»',
                'user_id' => auth()->id(),
            ]);

            return true;
        });
    }

    /** Totale ancora da addebitare (pubblicazioni dell'ultimo mese). */
    public static function pendingCents(Tenant $tenant): int
    {
        return (int) TenantUsageEntry::where('tenant_id', $tenant->id)->whereIn('status', ['pending', 'failed'])->sum('amount_cents');
    }

    public static function euro(int $cents): string
    {
        return number_format($cents / 100, $cents % 100 === 0 ? 0 : 2, ',', '.').' €';
    }
}
