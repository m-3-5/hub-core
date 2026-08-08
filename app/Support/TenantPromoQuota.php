<?php

namespace App\Support;

use App\Models\Tenant;

class TenantPromoQuota
{
    /**
     * Privato è gratis a tempo indeterminato con una piccola quota che si rinnova ogni mese
     * (niente scadenza demo). Azienda/Ente restano sulla quota totale storica.
     */
    public static function isMonthly(Tenant $tenant): bool
    {
        return $tenant->type === 'privato';
    }

    public static function includedLimit(Tenant $tenant): int
    {
        $bonus = (int) ($tenant->settings['bonus_promo_credits'] ?? 0);

        if (self::isMonthly($tenant)) {
            return (int) config('module_pricing.promo.included_per_month', 1) + $bonus;
        }

        return (int) ($tenant->settings['promo_included_quota'] ?? config('hub.promo_included_quota', 5)) + $bonus;
    }

    public static function usedCount(Tenant $tenant): int
    {
        if (self::isMonthly($tenant)) {
            return $tenant->promos()
                ->whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
                ->count();
        }

        return $tenant->promos()->count();
    }

    public static function remaining(Tenant $tenant): int
    {
        return max(0, self::includedLimit($tenant) - self::usedCount($tenant));
    }

    public static function hasIncludedSlot(Tenant $tenant): bool
    {
        return self::remaining($tenant) > 0;
    }

    /** Aggiunge slot bonus permanenti alla quota inclusa (es. omaggio da campagna email). */
    public static function grantBonusCredits(Tenant $tenant, int $amount): void
    {
        $settings = $tenant->settings ?? [];
        $settings['bonus_promo_credits'] = (int) ($settings['bonus_promo_credits'] ?? 0) + $amount;
        $tenant->update(['settings' => $settings]);
    }
}
