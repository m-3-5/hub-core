<?php

namespace M35\HubPayments\Support;

use App\Models\Tenant;

/**
 * Domini su cui un tenant può far tornare il cliente dopo il pagamento (success_url / cancel_url).
 * Derivati dai dati del tenant (sito, workspace, dominio) più l'impostazione facoltativa
 * settings.checkout_allowed_hosts. Nessun elenco cablato per cliente.
 */
class TenantReturnHosts
{
    /** @return array<int, string> */
    public static function for(Tenant $tenant): array
    {
        $hosts = [];

        foreach ([$tenant->website, $tenant->domain] as $apex) {
            $host = self::hostOf($apex);

            if ($host) {
                $hosts[] = $host;
                $hosts[] = str_starts_with($host, 'www.') ? substr($host, 4) : 'www.'.$host;
            }
        }

        foreach ([$tenant->workspace_url, config('hub.workspace.'.$tenant->slug.'.url')] as $url) {
            $host = self::hostOf($url);

            if ($host) {
                $hosts[] = $host;
            }
        }

        foreach ((array) ($tenant->settings['checkout_allowed_hosts'] ?? []) as $extra) {
            $host = self::hostOf($extra);

            if ($host) {
                $hosts[] = $host;
            }
        }

        return array_values(array_unique($hosts));
    }

    public static function allows(Tenant $tenant, string $url): bool
    {
        $parts = parse_url($url);

        if (! is_array($parts) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $scheme = strtolower($parts['scheme'] ?? '');

        if ($scheme !== 'https' && ! ($scheme === 'http' && app()->environment('local', 'testing'))) {
            return false;
        }

        return in_array(strtolower($parts['host']), self::for($tenant), true);
    }

    private static function hostOf(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $host = parse_url(str_contains($value, '://') ? $value : 'https://'.$value, PHP_URL_HOST);

        return $host ? strtolower($host) : null;
    }
}
