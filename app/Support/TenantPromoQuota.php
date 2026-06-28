<?php

namespace App\Support;

use App\Models\Tenant;

class TenantPromoQuota
{
    public static function includedLimit(Tenant $tenant): int
    {
        return (int) ($tenant->settings['promo_included_quota'] ?? config('hub.promo_included_quota', 5));
    }

    public static function usedCount(Tenant $tenant): int
    {
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
}
