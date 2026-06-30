<?php

namespace App\Support;

use App\Models\Tenant;

class HubModules
{
    /** @return array<int, array{key: string, label: string, description: string, emoji: string, active: bool, url: ?string}> */
    public function forTenant(Tenant $tenant): array
    {
        $enabled = $tenant->settings['modules'] ?? null;

        return collect(config('hub.modules'))
            ->map(function (array $module) use ($tenant, $enabled) {
                $active = $enabled[$module['key']] ?? $module['active'];

                return [
                    ...$module,
                    'active' => (bool) $active,
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
            default => null,
        };
    }
}
