<?php

namespace M35\HubPayments\Support;

use App\Models\Tenant;
use M35\HubPayments\Models\PayableService;

class TenantServiceQuota
{
    public static function includedLimit(Tenant $tenant): int
    {
        return (int) ($tenant->settings['services_included_quota']
            ?? config('hub-payments.services_included_quota', 3));
    }

    public static function usedCount(Tenant $tenant): int
    {
        return PayableService::query()
            ->where('tenant_id', $tenant->id)
            ->where('type', 'service')
            ->count();
    }

    public static function remaining(Tenant $tenant): int
    {
        return max(0, self::includedLimit($tenant) - self::usedCount($tenant));
    }

    public static function hasIncludedSlot(Tenant $tenant): bool
    {
        return self::remaining($tenant) > 0;
    }

    public static function paidUnlockPrice(Tenant $tenant): int
    {
        return (int) ($tenant->settings['services_paid_price']
            ?? config('hub-payments.services_paid_price', 9));
    }
}
