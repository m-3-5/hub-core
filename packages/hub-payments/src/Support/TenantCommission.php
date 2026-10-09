<?php

namespace M35\HubPayments\Support;

use App\Models\Tenant;

/**
 * Commissione di M 3.5 sulle vendite fatte dalle pagine pubbliche di inm35.it (canale "hub").
 * Per tenant, in tenants.settings.commission: percentuale e/o importo fisso per ordine. Default 0 = nessuna commissione.
 * Non cambia i prezzi pagati dal cliente: si registra sull'ordine e si addebita al tenant a fine mese.
 */
class TenantCommission
{
    public const CHANNEL = 'hub';

    public static function percent(Tenant $tenant): float
    {
        return max(0.0, min(100.0, (float) ($tenant->settings['commission']['percent'] ?? 0)));
    }

    public static function fixedCents(Tenant $tenant): int
    {
        return max(0, (int) ($tenant->settings['commission']['fixed_cents'] ?? 0));
    }

    public static function isActive(Tenant $tenant): bool
    {
        return self::percent($tenant) > 0 || self::fixedCents($tenant) > 0;
    }

    /** Commissione dovuta su un ordine: zero fuori dal canale hub, mai oltre l'importo dell'ordine. */
    public static function compute(Tenant $tenant, string $channel, int $amountCents): int
    {
        if ($channel !== self::CHANNEL || $amountCents <= 0) {
            return 0;
        }

        $commission = (int) round($amountCents * self::percent($tenant) / 100) + self::fixedCents($tenant);

        return min($commission, $amountCents);
    }

    public static function store(Tenant $tenant, float $percent, int $fixedCents): void
    {
        $settings = $tenant->settings ?? [];
        $settings['commission'] = [
            'percent' => max(0.0, min(100.0, $percent)),
            'fixed_cents' => max(0, $fixedCents),
        ];

        $tenant->forceFill(['settings' => $settings])->save();
    }
}
