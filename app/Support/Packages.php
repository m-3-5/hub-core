<?php

namespace App\Support;

use App\Models\Tenant;
use App\Models\TenantUsageEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Pacchetti Base / Premium / Business (Max 28/11/2026): facoltativi, per chi pubblica tanto.
 * - Si pagano dal credito (si paga solo con le ricariche), per 1, 3, 6 o 12 mesi con sconto.
 * - Ogni «mese» (30 giorni dall'acquisto) ha un tetto di promo+prodotti+servizi inclusi; oltre si paga over_cents (0,50 €).
 * - Premium e Business includono le opzioni del sito (SEO, IA, indicizza) e alcune lingue; il sito stesso è incluso in tutti.
 * - Si rinnovano da soli (hub:renew-premium) finché il cliente vuole e c'è credito.
 * - Cambiare pacchetto mentre uno è attivo: si restituisce in credito la parte non usata di quello vecchio.
 * - Uscendo: exit_cents (0,80 €) a pubblicazione per exit_days giorni, poi il prezzo normale.
 * Stato in settings.ppu_premium: plan, months, paid_cents, bought_at, until, renew, started_at, ended_at.
 * Promemoria completo: docs/FUNZIONI-INM35.md
 */
class Packages
{
    public static function enabled(): bool
    {
        return PayPerUse::enabled() && (bool) self::cfg('enabled', true);
    }

    /** @return array<string, array<string, mixed>> */
    public static function plans(): array
    {
        return (array) self::cfg('plans', []);
    }

    /** @return array<string, mixed>|null */
    public static function plan(string $key): ?array
    {
        return self::plans()[$key] ?? null;
    }

    public static function monthlyCents(string $key): int
    {
        return (int) (self::plan($key)['cents'] ?? 0);
    }

    /** @return array<int, int> mesi => sconto % */
    public static function monthOptions(): array
    {
        return (array) self::cfg('months', [1 => 0]);
    }

    /** Prezzo per $months mesi, con lo sconto, arrotondato ai 10 centesimi. */
    public static function priceCents(string $key, int $months): int
    {
        $discount = (int) (self::monthOptions()[$months] ?? 0);
        $raw = self::monthlyCents($key) * $months * (100 - $discount) / 100;

        return (int) (round($raw / 10) * 10);
    }

    public static function overCents(): int
    {
        return max(0, (int) self::cfg('over_cents', 50));
    }

    public static function exitCents(): int
    {
        return max(0, (int) self::cfg('exit_cents', 80));
    }

    /** @return array<string, mixed> */
    public static function state(Tenant $tenant): array
    {
        return (array) ($tenant->settings['ppu_premium'] ?? []);
    }

    public static function active(Tenant $tenant): bool
    {
        $until = self::state($tenant)['until'] ?? null;

        return $until !== null && now()->lt(Carbon::parse($until));
    }

    /** Il pacchetto attivo (con la chiave in «key»), o null. */
    public static function current(Tenant $tenant): ?array
    {
        if (! self::active($tenant)) {
            return null;
        }
        $key = self::state($tenant)['plan'] ?? 'premium';
        $plan = self::plan($key);

        return $plan ? $plan + ['key' => $key] : null;
    }

    public static function exitActive(Tenant $tenant): bool
    {
        $s = self::state($tenant);
        if (self::active($tenant) || empty($s['until'])) {
            return false;
        }

        return now()->lt(Carbon::parse($s['until'])->addDays(max(0, (int) self::cfg('exit_days', 30))));
    }

    /** Inizio del «mese» in corso: ogni 30 giorni dall'acquisto. */
    public static function periodStart(Tenant $tenant): Carbon
    {
        $bought = Carbon::parse(self::state($tenant)['bought_at'] ?? now());
        $days = max(1, (int) self::cfg('days', 30));
        $n = intdiv(max(0, (int) $bought->diffInDays(now())), $days);

        return $bought->copy()->addDays($n * $days);
    }

    public static function itemsUsed(Tenant $tenant): int
    {
        return TenantUsageEntry::where('tenant_id', $tenant->id)->where('reason', 'premium')
            ->whereIn('item_type', array_keys(PayPerUse::KINDS))
            ->where('created_at', '>=', self::periodStart($tenant))->count();
    }

    public static function itemsLeft(Tenant $tenant): int
    {
        $plan = self::current($tenant);

        return $plan ? max(0, (int) $plan['items'] - self::itemsUsed($tenant)) : 0;
    }

    public static function langsLeft(Tenant $tenant): int
    {
        $plan = self::current($tenant);
        if (! $plan) {
            return 0;
        }
        $used = TenantUsageEntry::where('tenant_id', $tenant->id)->where('reason', 'premium')->where('item_type', 'like', 'lang_%')
            ->where('created_at', '>=', Carbon::parse(self::state($tenant)['started_at'] ?? now()))->count();

        return max(0, (int) $plan['langs'] - $used);
    }

    /**
     * Prezzo con il pacchetto attivo, o null se il pacchetto non c'è o non copre questa voce (si usa il prezzo normale).
     */
    public static function priceFor(Tenant $tenant, string $kind): ?int
    {
        $plan = self::current($tenant);
        if (! $plan) {
            return null;
        }

        if (isset(PayPerUse::KINDS[$kind])) {
            return self::itemsLeft($tenant) > 0 ? 0 : self::overCents();
        }
        if ($kind === PayPerUse::SITE) {
            return 0;
        }
        if (str_starts_with($kind, 'addon_')) {
            return $plan['addons'] ? 0 : null;
        }
        if (str_starts_with($kind, 'lang_')) {
            return self::langsLeft($tenant) > 0 ? 0 : null;
        }
        if ($kind === 'site_langs') {
            $wanted = class_exists(\M35\HubSitebuilder\Services\SiteTranslations::class)
                ? count(\M35\HubSitebuilder\Services\SiteTranslations::wanted($tenant->generatedSite)) : 0;

            return max(0, $wanted - self::langsLeft($tenant)) * max(0, (int) config('services.hub_billing.pay_per_use.site_cents', 100));
        }

        return null;
    }

    /**
     * Compra (o rinnova) un pacchetto per $months mesi, dal credito spendibile.
     * Stesso pacchetto attivo: i mesi si aggiungono in coda. Pacchetto diverso: la parte non usata del vecchio torna in credito.
     * false = credito non basta (o pacchetto/mesi non validi).
     */
    public static function buy(Tenant $tenant, string $key, int $months = 1, bool $renewal = false): bool
    {
        if (! self::enabled() || ! self::plan($key) || ! array_key_exists($months, self::monthOptions())) {
            return false;
        }

        $entries = DB::transaction(function () use ($tenant, $key, $months, $renewal) {
            Tenant::whereKey($tenant->id)->lockForUpdate()->first();
            $tenant = $tenant->fresh();
            $price = self::priceCents($key, $months);
            $old = self::state($tenant);
            $active = self::active($tenant);
            $same = $active && ($old['plan'] ?? 'premium') === $key;

            $refund = 0;
            if ($active && ! $same) {
                $start = Carbon::parse($old['bought_at'] ?? now());
                $end = Carbon::parse($old['until']);
                $total = max(1, $start->diffInSeconds($end));
                $refund = (int) floor((int) ($old['paid_cents'] ?? 0) * max(0, now()->diffInSeconds($end)) / $total);
            }

            if (PayPerUse::spendableCents($tenant) + $refund < $price) {
                return null;
            }

            $done = [];
            if ($refund > 0) {
                PayPerUse::moveCredit($tenant, $refund);
                $done[] = TenantUsageEntry::create([
                    'tenant_id' => $tenant->id, 'amount_cents' => $refund, 'reason' => 'premium_refund', 'status' => 'topup', 'billed_at' => now(),
                    'description' => 'Parte non usata del pacchetto '.(self::plan($old['plan'] ?? 'premium')['label'] ?? 'Premium'), 'user_id' => auth()->id(),
                ]);
            }

            PayPerUse::moveCredit($tenant, -$price);
            $days = max(1, (int) self::cfg('days', 30));
            $from = $same ? Carbon::parse($old['until']) : now();
            $until = $from->copy()->addDays($days * $months);

            $settings = $tenant->fresh()->settings ?? [];
            $settings['ppu_premium'] = [
                'plan' => $key,
                'months' => $months,
                'paid_cents' => $same ? (int) ($old['paid_cents'] ?? 0) + $price : $price,
                'bought_at' => $same ? ($old['bought_at'] ?? now()->toIso8601String()) : now()->toIso8601String(),
                'until' => $until->toIso8601String(),
                'renew' => true,
                'started_at' => $active ? ($old['started_at'] ?? now()->toIso8601String()) : now()->toIso8601String(),
            ];
            $tenant->forceFill(['settings' => $settings])->save();

            $label = self::plan($key)['label'];
            $done[] = TenantUsageEntry::create([
                'tenant_id' => $tenant->id, 'amount_cents' => $price, 'reason' => 'premium_fee', 'status' => 'credit',
                'description' => ($renewal ? 'Rinnovo' : 'Pacchetto').' '.$label.' · '.$months.' '.($months === 1 ? 'mese' : 'mesi').' fino al '.$until->timezone(config('app.timezone'))->format('d/m/Y'),
                'user_id' => auth()->id(),
            ]);

            return $done;
        });

        if (! $entries) {
            return false;
        }
        CreditNotices::receipt($tenant->fresh(), end($entries));

        return true;
    }

    /** Rinnovo automatico: stesso pacchetto e stessi mesi. */
    public static function renew(Tenant $tenant): bool
    {
        $s = self::state($tenant);

        return self::buy($tenant, $s['plan'] ?? 'premium', (int) ($s['months'] ?? 1), renewal: true);
    }

    public static function setRenew(Tenant $tenant, bool $renew): void
    {
        $settings = $tenant->settings ?? [];
        if (! isset($settings['ppu_premium']) || ($renew && ! self::active($tenant))) {
            return;
        }
        $settings['ppu_premium']['renew'] = $renew;
        if ($renew) {
            unset($settings['ppu_premium']['ended_at']);
        }
        $tenant->forceFill(['settings' => $settings])->save();
    }

    private static function cfg(string $key, mixed $default = null): mixed
    {
        return config('services.hub_billing.pay_per_use.premium.'.$key, $default);
    }
}
