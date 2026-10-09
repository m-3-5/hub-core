<?php

namespace M35\HubPayments\Support;

use App\Models\Tenant;
use Illuminate\Support\Facades\Crypt;

/** Webhook Stripe del conto di ciascun tenant (segreto di firma cifrato in tenants.settings.stripe). */
class TenantStripeWebhook
{
    /** Eventi che l'hub ascolta: pagamento riuscito, anche per metodi asincroni. */
    public const EVENTS = ['checkout.session.completed', 'checkout.session.async_payment_succeeded'];

    public static function url(Tenant $tenant): string
    {
        return route('api.stripe.tenant-webhook', ['tenantSlug' => $tenant->slug]);
    }

    public static function secret(Tenant $tenant): ?string
    {
        $encrypted = $tenant->settings['stripe']['webhook_secret'] ?? null;

        if (! is_string($encrypted) || $encrypted === '') {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function isConfigured(Tenant $tenant): bool
    {
        return self::secret($tenant) !== null;
    }

    public static function endpointId(Tenant $tenant): ?string
    {
        $id = $tenant->settings['stripe']['webhook_endpoint_id'] ?? null;

        return is_string($id) && $id !== '' ? $id : null;
    }

    public static function lastEventAt(Tenant $tenant): ?string
    {
        return $tenant->settings['stripe']['webhook_last_event_at'] ?? null;
    }

    public static function store(Tenant $tenant, string $signingSecret, ?string $endpointId = null): void
    {
        $settings = $tenant->settings ?? [];
        $settings['stripe'] = array_merge($settings['stripe'] ?? [], [
            'webhook_secret' => Crypt::encryptString(trim($signingSecret)),
            'webhook_endpoint_id' => $endpointId,
            'webhook_last_event_at' => null,
        ]);

        $tenant->forceFill(['settings' => $settings])->save();
    }

    public static function touch(Tenant $tenant): void
    {
        $settings = $tenant->settings ?? [];
        $settings['stripe'] = array_merge($settings['stripe'] ?? [], ['webhook_last_event_at' => now()->toIso8601String()]);

        $tenant->forceFill(['settings' => $settings])->save();
    }

    /**
     * Verifica l'intestazione Stripe-Signature: "t=<ts>,v1=<hmac>[,v1=...]" con HMAC-SHA256 di "<ts>.<corpo>".
     */
    public static function verifySignature(string $payload, ?string $header, string $secret, int $tolerance = 300): bool
    {
        if (! $header) {
            return false;
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);

            if ($key === 't') {
                $timestamp = $value;
            } elseif ($key === 'v1' && $value !== null) {
                $signatures[] = $value;
            }
        }

        if ($timestamp === null || ! ctype_digit($timestamp) || ! $signatures || abs(time() - (int) $timestamp) > $tolerance) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }
}
