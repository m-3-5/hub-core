<?php

namespace App\Support;

use App\Models\Tenant;
use Illuminate\Support\Facades\Crypt;

/**
 * Indirizzo del sito di ciascun tenant che l'hub avvisa quando cambiano catalogo o promo
 * (tenants.settings.site_sync). Senza impostazione per tenant valgono i vecchi indirizzi globali del .env.
 *
 * - url: dove arrivano gli avvisi (POST firmato con X-Hub-Signature = sha256 HMAC del corpo)
 * - secret: segreto di firma dedicato (cifrato); se manca vale HUB_WEBHOOK_SECRET
 * - promos: le promo avvisano anche questo indirizzo (default sì)
 * - legacy_promos: le promo avvisano anche il vecchio indirizzo globale HUB_WEBHOOK_URL (default sì, finché il vecchio sito convive)
 */
class TenantSiteSync
{
    public static function url(Tenant $tenant): ?string
    {
        $url = $tenant->settings['site_sync']['url'] ?? null;

        return is_string($url) && $url !== '' ? $url : null;
    }

    public static function secret(Tenant $tenant): ?string
    {
        $encrypted = $tenant->settings['site_sync']['secret'] ?? null;

        if (! is_string($encrypted) || $encrypted === '') {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function sendsPromos(Tenant $tenant): bool
    {
        return (bool) ($tenant->settings['site_sync']['promos'] ?? true);
    }

    public static function sendsLegacyPromos(Tenant $tenant): bool
    {
        return (bool) ($tenant->settings['site_sync']['legacy_promos'] ?? true);
    }

    /**
     * @param  array{url?: string, secret?: ?string, promos?: bool, legacy_promos?: bool}  $changes
     */
    public static function update(Tenant $tenant, array $changes): void
    {
        $current = $tenant->settings['site_sync'] ?? [];

        if (array_key_exists('secret', $changes)) {
            $changes['secret'] = $changes['secret'] ? Crypt::encryptString($changes['secret']) : null;
        }

        $settings = $tenant->settings ?? [];
        $settings['site_sync'] = array_merge($current, $changes);
        $tenant->forceFill(['settings' => $settings])->save();
    }

    public static function clear(Tenant $tenant): void
    {
        $settings = $tenant->settings ?? [];
        unset($settings['site_sync']);
        $tenant->forceFill(['settings' => $settings])->save();
    }

    /** Solo https (http ammesso in locale/test). */
    public static function isValidUrl(string $url): bool
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return $scheme === 'https' || ($scheme === 'http' && app()->environment(['local', 'testing']));
    }
}
