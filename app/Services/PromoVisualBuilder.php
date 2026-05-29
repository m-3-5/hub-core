<?php

namespace App\Services;

use App\Models\Promo;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PromoVisualBuilder
{
    public function __construct(
        private GeminiImageGenerator $geminiImages,
    ) {}

    /**
     * @return array<string, string>
     */
    public function build(Tenant $tenant, Promo $promo, string $originalStoragePath, ?string $referenceAbsolutePath = null): array
    {
        $dir = pathinfo($originalStoragePath, PATHINFO_DIRNAME);
        $variants = [
            'original' => $originalStoragePath,
            'flyer' => $originalStoragePath,
        ];

        $heroSvg = $this->buildHeroSvg($tenant, $promo);
        $heroSvgPath = $dir.'/hero.svg';
        $this->writePublic($heroSvgPath, $heroSvg);
        $variants['hero_svg'] = $heroSvgPath;

        $ogSvg = $this->buildOgSvg($tenant, $promo);
        $ogSvgPath = $dir.'/og.svg';
        $this->writePublic($ogSvgPath, $ogSvg);
        $variants['og'] = $ogSvgPath;

        if ($referenceAbsolutePath && is_file($referenceAbsolutePath)) {
            try {
                $aiPath = $this->geminiImages->generateHeroBanner(
                    $tenant,
                    $promo,
                    $referenceAbsolutePath,
                    $dir.'/hero-ai.jpg',
                );

                if ($aiPath) {
                    $variants['hero'] = $aiPath;
                }
            } catch (\Throwable $e) {
                Log::warning('Gemini image generation skipped', ['message' => $e->getMessage()]);
            }
        }

        return $variants;
    }

    private function buildHeroSvg(Tenant $tenant, Promo $promo): string
    {
        $color = $this->escapeXml($tenant->primary_color);

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 700" preserveAspectRatio="xMidYMid slice">
  <defs>
    <linearGradient id="bg" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" style="stop-color:{$color};stop-opacity:1" />
      <stop offset="100%" style="stop-color:#1a0a14;stop-opacity:1" />
    </linearGradient>
    <filter id="blur"><feGaussianBlur stdDeviation="40"/></filter>
  </defs>
  <rect width="1200" height="700" fill="url(#bg)"/>
  <circle cx="200" cy="120" r="140" fill="#ffffff" opacity="0.08" filter="url(#blur)"/>
  <circle cx="1000" cy="580" r="180" fill="#ffffff" opacity="0.06" filter="url(#blur)"/>
  <path d="M0 520 Q300 420 600 500 T1200 460 L1200 700 L0 700 Z" fill="#ffffff" opacity="0.07"/>
</svg>
SVG;
    }

    private function buildOgSvg(Tenant $tenant, Promo $promo): string
    {
        $color = $this->escapeXml($tenant->primary_color);
        $title = $this->escapeXml(Str::limit($promo->title, 60));
        $subtitle = $this->escapeXml(Str::limit(strip_tags($promo->description ?? ''), 90));

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 630">
  <rect width="1200" height="630" fill="{$color}"/>
  <rect x="0" y="0" width="1200" height="630" fill="#000000" opacity="0.15"/>
  <text x="60" y="280" fill="#ffffff" font-family="Georgia, serif" font-size="52" font-weight="700">{$title}</text>
  <text x="60" y="350" fill="#ffffff" opacity="0.9" font-family="system-ui, sans-serif" font-size="24">{$subtitle}</text>
  <text x="60" y="560" fill="#ffffff" font-family="system-ui, sans-serif" font-size="20" font-weight="600">{$this->escapeXml($tenant->name)}</text>
</svg>
SVG;
    }

    private function escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function writePublic(string $relativePath, string $contents): void
    {
        $full = storage_path('app/public/'.$relativePath);
        $directory = dirname($full);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($full, $contents);
    }
}
