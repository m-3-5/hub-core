<?php

namespace App\Support;

use App\Models\Tenant;

class HubModules
{
    /** @return array<int, array{key: string, label: string, description: string, emoji: string, active: bool, fits_type: bool, url: ?string}> */
    public function forTenant(Tenant $tenant): array
    {
        $enabled = $tenant->settings['modules'] ?? null;
        $tenantType = $tenant->type ?: 'azienda';

        return collect(config('hub.modules'))
            ->map(function (array $module) use ($tenant, $enabled, $tenantType) {
                $builtAndEnabled = (bool) ($enabled[$module['key']] ?? $module['active']);
                $fitsType = in_array($tenantType, $module['for_types'] ?? ['azienda', 'privato', 'ente'], true);
                $active = $builtAndEnabled && $fitsType;

                return [
                    ...$module,
                    'active' => $active,
                    'fits_type' => $fitsType,
                    'url' => $active ? $this->urlFor($module['key'], $tenant) : null,
                ];
            })
            ->values()
            ->all();
    }

    private function urlFor(string $key, Tenant $tenant): ?string
    {
        return match ($key) {
            'promo' => route('admin.promos.index', $tenant),
            'services' => route('admin.services.index', $tenant),
            'billing' => route('admin.billing.show', $tenant),
            default => null,
        };
    }
}
