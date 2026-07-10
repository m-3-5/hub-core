<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class TenantBrandManager
{
    public function logoPath(Tenant $tenant): ?string
    {
        $path = $tenant->settings['brand']['logo_path'] ?? null;

        return is_string($path) && Storage::disk('public')->exists($path) ? $path : null;
    }

    public function logoUrl(Tenant $tenant): ?string
    {
        $path = $this->logoPath($tenant);

        return $path ? Storage::disk('public')->url($path) : null;
    }

    public function hasLogo(Tenant $tenant): bool
    {
        return $this->logoPath($tenant) !== null;
    }

    public function storeLogo(Tenant $tenant, UploadedFile $file, bool $persist = true): string
    {
        $path = $file->store("brand/{$tenant->slug}", 'public');

        if ($persist) {
            $settings = $tenant->settings ?? [];
            $settings['brand'] = array_merge($settings['brand'] ?? [], [
                'logo_path' => $path,
                'updated_at' => now()->toIso8601String(),
            ]);
            $tenant->update(['settings' => $settings]);
        }

        return $path;
    }

    public function absolutePath(?string $relativePath): ?string
    {
        if (! $relativePath) {
            return null;
        }

        $full = storage_path('app/public/'.$relativePath);

        return is_file($full) ? $full : null;
    }

    public function color(Tenant $tenant): ?string
    {
        $color = $tenant->settings['brand']['color'] ?? null;

        return is_string($color) && preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : null;
    }

    public function hasColor(Tenant $tenant): bool
    {
        return $this->color($tenant) !== null;
    }

    public function storeColor(Tenant $tenant, string $color): void
    {
        $settings = $tenant->settings ?? [];
        $settings['brand'] = array_merge($settings['brand'] ?? [], [
            'color' => $color,
            'updated_at' => now()->toIso8601String(),
        ]);
        $tenant->update(['settings' => $settings]);
    }
}
