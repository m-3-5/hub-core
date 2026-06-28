<?php

namespace App\Services;

use App\Models\Promo;
use App\Models\Tenant;
use Illuminate\Support\Facades\File;

class PromoVisualFallback
{
    /**
     * @param  array<string, mixed>  $variants
     * @return array<string, mixed>
     */
    public function ensureBaseVariants(array $variants, string $originalStoragePath): array
    {
        $variants['original'] = $variants['original'] ?? $originalStoragePath;
        $variants['flyer'] = $variants['flyer'] ?? $originalStoragePath;
        $variants['hero'] = $variants['hero'] ?? $originalStoragePath;
        $variants['og'] = $variants['og'] ?? ($variants['hero'] ?? $originalStoragePath);

        return $variants;
    }

    /**
     * @param  array<string, array{path: string, label: string, key: string}>  $decor
     * @return array<string, array{path: string, label: string, key: string}>
     */
    public function ensureDecor(
        Tenant $tenant,
        Promo $promo,
        array $decor,
        array $topics,
    ): array {
        $dir = pathinfo($promo->image_path, PATHINFO_DIRNAME);

        foreach ($topics as $slot => $meta) {
            if (isset($decor[$slot])) {
                continue;
            }

            $path = $this->writeDecorSvg(
                $tenant,
                $dir.'/fallback-'.$slot.'.svg',
                $meta['label'] ?? 'Offerta',
                $meta['key'] ?? 'beauty',
            );

            if ($path) {
                $decor[$slot] = [
                    'path' => $path,
                    'label' => $meta['label'] ?? 'Offerta',
                    'key' => $meta['key'] ?? 'beauty',
                ];
            }
        }

        return $decor;
    }

    public function writeDecorSvg(Tenant $tenant, string $storagePath, string $label, string $topicKey): ?string
    {
        $absolute = storage_path('app/public/'.$storagePath);
        File::ensureDirectoryExists(dirname($absolute));

        $color = $tenant->primary_color ?: '#e91e8c';
        $emoji = match ($topicKey) {
            'hair' => '✂️',
            'body' => '💆',
            'nails' => '💅',
            'spa' => '🧖',
            default => '✨',
        };
        $safeLabel = htmlspecialchars(mb_substr($label, 0, 40), ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="800" height="600" viewBox="0 0 800 600">
  <defs>
    <linearGradient id="bg" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" style="stop-color:{$color};stop-opacity:0.95"/>
      <stop offset="100%" style="stop-color:#ffffff;stop-opacity:1"/>
    </linearGradient>
  </defs>
  <rect width="800" height="600" fill="url(#bg)"/>
  <circle cx="400" cy="220" r="88" fill="#ffffff" fill-opacity="0.22"/>
  <text x="400" y="245" text-anchor="middle" font-size="72">{$emoji}</text>
  <text x="400" y="360" text-anchor="middle" font-family="Georgia, serif" font-size="42" fill="#1f1a24">{$safeLabel}</text>
  <text x="400" y="410" text-anchor="middle" font-family="Arial, sans-serif" font-size="22" fill="#5c5563">{$safeLabel}</text>
</svg>
SVG;

        if (file_put_contents($absolute, $svg) === false) {
            return null;
        }

        return $storagePath;
    }
}
