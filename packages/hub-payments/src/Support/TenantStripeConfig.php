<?php

namespace M35\HubPayments\Support;

use App\Models\Tenant;
use Illuminate\Support\Facades\Crypt;

class TenantStripeConfig
{
    public static function isConfigured(Tenant $tenant): bool
    {
        return self::secretKey($tenant) !== null;
    }

    public static function secretKey(Tenant $tenant): ?string
    {
        $encrypted = $tenant->settings['stripe']['secret_key'] ?? null;

        if (! is_string($encrypted) || $encrypted === '') {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function publishableKey(Tenant $tenant): ?string
    {
        $key = $tenant->settings['stripe']['publishable_key'] ?? null;

        return is_string($key) && $key !== '' ? $key : null;
    }

    public static function store(Tenant $tenant, string $secretKey, ?string $publishableKey = null): void
    {
        $settings = $tenant->settings ?? [];
        $settings['stripe'] = [
            'secret_key' => Crypt::encryptString(trim($secretKey)),
            'publishable_key' => $publishableKey ? trim($publishableKey) : ($settings['stripe']['publishable_key'] ?? null),
        ];

        $tenant->forceFill(['settings' => $settings])->save();
    }

    public static function maskedSecret(Tenant $tenant): ?string
    {
        $key = self::secretKey($tenant);

        if (! $key) {
            return null;
        }

        if (strlen($key) <= 12) {
            return str_repeat('•', strlen($key));
        }

        return substr($key, 0, 7).'…'.substr($key, -4);
    }
}
