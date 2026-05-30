<?php

namespace App\Services;

use App\Models\Tenant;

class PromoThemeIcons
{
    /** @return array<int, array{key: string, label: string, svg: string}> */
    public function iconsForPromo(array $offers, ?string $description, Tenant $tenant): array
    {
        $haystack = strtolower(
            collect($offers)->map(fn ($o) => ($o['name'] ?? '').' '.($o['detail'] ?? ''))->implode(' ')
            .' '.($description ?? '')
        );

        $matched = [];

        foreach ($this->catalogMeta() as $item) {
            foreach ($item['keywords'] as $keyword) {
                if (str_contains($haystack, $keyword)) {
                    $matched[$item['key']] = $item;
                    break;
                }
            }
        }

        if ($matched === []) {
            $matched['beauty'] = $this->catalogMeta()['beauty'];
        }

        return collect($matched)->map(fn (array $item) => [
            'key' => $item['key'],
            'label' => $item['label'],
            'svg' => $this->svg($item['key'], $tenant),
        ])->values()->all();
    }

    public function svg(string $key, Tenant $tenant): string
    {
        $ai = app(GeminiThemeIconGenerator::class)->readCached($tenant, $key);

        if ($ai) {
            return $this->decorateSvg($ai, $key);
        }

        return $this->fallbackSvg($key);
    }

    public function iconKeyForOffer(array $offer): string
    {
        $text = strtolower(($offer['name'] ?? '').' '.($offer['detail'] ?? ''));

        foreach ($this->catalogMeta() as $item) {
            foreach ($item['keywords'] as $keyword) {
                if (str_contains($text, $keyword)) {
                    return $item['key'];
                }
            }
        }

        return 'beauty';
    }

    /** @return array<string, array{key: string, label: string, keywords: array<string>}> */
    private function catalogMeta(): array
    {
        return [
            'hair' => [
                'key' => 'hair',
                'label' => 'Parrucchiere',
                'keywords' => ['piega', 'parrucch', 'capelli', 'hair', 'taglio', 'colore', 'acconci'],
            ],
            'body' => [
                'key' => 'body',
                'label' => 'Trattamenti corpo',
                'keywords' => ['corpo', 'sedute', 'dimagr', 'massag', 'estetica', 'trattamento', 'cellulite'],
            ],
            'nails' => [
                'key' => 'nails',
                'label' => 'Beauty & nails',
                'keywords' => ['unghie', 'nail', 'manicure', 'pedicure'],
            ],
            'spa' => [
                'key' => 'spa',
                'label' => 'Benessere',
                'keywords' => ['benessere', 'spa', 'relax', 'viso', 'detox', 'rigener'],
            ],
            'beauty' => [
                'key' => 'beauty',
                'label' => 'Centro bellezza',
                'keywords' => ['bellezza', 'beauty', 'immagine', 'promoz', 'centro'],
            ],
            'mirror' => [
                'key' => 'mirror',
                'label' => 'Stile & immagine',
                'keywords' => ['stile', 'look', 'make', 'trucco'],
            ],
        ];
    }

    private function fallbackSvg(string $key): string
    {
        $inline = match ($key) {
            'hair' => <<<'SVG'
<path fill="currentColor" fill-opacity="0.95" d="M46 17c6 0 10 4 11 9 1 4-1 8-4 10 2 2 3 5 3 8 0 6-4 11-9 13 3 2 5 5 6 9 1 3 0 6-2 8H34c-2-2-3-5-2-8 1-4 3-7 6-9-5-2-9-7-9-13 1-5 5-9 11-9z"/>
<path fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" d="M32 22C20 26 12 36 10 48"/>
<path fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" opacity="0.72" d="M26 26C16 32 10 42 10 54"/>
SVG,
            'body' => <<<'SVG'
<path fill="currentColor" fill-opacity="0.9" d="M40 12c-5 0-9 4-9 9 0 3 1 6 3 8-7 3-12 11-13 21-1 7 1 14 5 19 3 4 7 6 12 6h4c5 0 9-2 12-6 4-5 6-12 5-19-1-10-6-18-13-21 2-2 3-5 3-8 0-5-4-9-9-9z"/>
SVG,
            'nails' => <<<'SVG'
<path fill="currentColor" fill-opacity="0.88" d="M26 54c-2-14 6-26 18-28 10-2 18 6 20 18 1 8-3 15-10 18-4 2-9 2-14 0-8-3-12-10-14-8z"/>
<path fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" d="M30 38c0-8 3-14 6-17M38 34c0-10 3-16 6-19M46 36c0-9 3-15 6-18"/>
SVG,
            'spa' => <<<'SVG'
<path fill="currentColor" fill-opacity="0.9" d="M48 16c7 0 12 5 12 12 0 4-2 8-5 10 3 3 5 8 5 13 0 9-7 16-16 16s-16-7-16-16c0-5 2-10 5-13-3-2-5-6-5-10 0-7 5-12 12-12z"/>
<path fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" opacity="0.5" d="M36 38c2 2 4 2 6 0"/>
SVG,
            'mirror' => <<<'SVG'
<path fill="currentColor" fill-opacity="0.88" d="M42 15c7 0 12 5 12 11 0 3-1 6-3 8 2 2 3 5 3 8 0 7-5 12-12 12s-12-5-12-12c0-3 1-6 3-8-2-2-3-5-3-8 0-6 5-11 12-11z"/>
<path fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" d="M14 34c3-9 10-15 18-17"/>
SVG,
            default => <<<'SVG'
<path fill="currentColor" fill-opacity="0.95" d="M46 17c6 0 10 4 11 9 1 4-1 8-4 10 2 2 3 5 3 8 0 6-4 11-9 13 3 2 5 5 6 9 1 3 0 6-2 8H34c-2-2-3-5-2-8 1-4 3-7 6-9-5-2-9-7-9-13 1-5 5-9 11-9z"/>
<path fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" opacity="0.65" d="M30 24C18 28 12 38 12 50"/>
SVG,
        };

        $file = resource_path("brand-icons/{$key}.svg");

        if (is_readable($file)) {
            $svg = trim((string) file_get_contents($file));

            if (str_contains($svg, '<svg')) {
                return $this->decorateSvg($svg, $key);
            }
        }

        return $this->wrap($key, $inline);
    }

    private function decorateSvg(string $svg, string $key): string
    {
        $svg = preg_replace('/promo-icon--[\w-]+/', 'promo-icon--'.$key, $svg) ?? $svg;

        if (! preg_match('/class\s*=/', $svg)) {
            $svg = preg_replace(
                '/<svg\b/',
                '<svg class="promo-icon promo-icon--'.$key.'" aria-hidden="true"',
                $svg,
                1
            ) ?? $svg;
        }

        return $svg;
    }

    private function wrap(string $id, string $paths): string
    {
        return <<<SVG
<svg class="promo-icon promo-icon--{$id}" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
{$paths}
</svg>
SVG;
    }
}
