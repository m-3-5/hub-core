<?php

namespace App\Services;

class PromoThemeIcons
{
    /** @return array<int, array{key: string, label: string, svg: string}> */
    public function iconsForPromo(array $offers, ?string $description = null): array
    {
        $haystack = strtolower(
            collect($offers)->map(fn ($o) => ($o['name'] ?? '').' '.($o['detail'] ?? ''))->implode(' ')
            .' '.($description ?? '')
        );

        $matched = [];

        foreach ($this->catalog() as $item) {
            foreach ($item['keywords'] as $keyword) {
                if (str_contains($haystack, $keyword)) {
                    $matched[$item['key']] = $item;
                    break;
                }
            }
        }

        if ($matched === []) {
            $matched['beauty'] = $this->catalog()['beauty'];
        }

        return array_values($matched);
    }

    public function svg(string $key): string
    {
        return $this->catalog()[$key]['svg'] ?? $this->catalog()['beauty']['svg'];
    }

    public function iconKeyForOffer(array $offer): string
    {
        $text = strtolower(($offer['name'] ?? '').' '.($offer['detail'] ?? ''));

        foreach ($this->catalog() as $item) {
            foreach ($item['keywords'] as $keyword) {
                if (str_contains($text, $keyword)) {
                    return $item['key'];
                }
            }
        }

        return 'beauty';
    }

    /** @return array<string, array{key: string, label: string, keywords: array<string>, svg: string}> */
    private function catalog(): array
    {
        return [
            'hair' => [
                'key' => 'hair',
                'label' => 'Parrucchiere',
                'keywords' => ['piega', 'parrucch', 'capelli', 'hair', 'taglio', 'colore', 'acconci'],
                'svg' => $this->wrap('hair', <<<'SVG'
<defs>
  <linearGradient id="hair-g" x1="20" y1="20" x2="60" y2="60" gradientUnits="userSpaceOnUse">
    <stop stop-color="currentColor" stop-opacity="0.35"/>
    <stop offset="1" stop-color="currentColor" stop-opacity="0.08"/>
  </linearGradient>
</defs>
<circle cx="40" cy="40" r="35" fill="url(#hair-g)"/>
<path d="M18 50c4-14 12-22 22-22s18 8 22 22" stroke="currentColor" stroke-width="2" stroke-linecap="round" fill="none"/>
<path d="M24 32c0-6 4-11 10-11s10 5 10 11" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
<path d="M20 38c6-4 12-6 20-6s14 2 20 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" opacity="0.4"/>
<g stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
  <path d="M46 16l16 12"/>
  <path d="M46 16l-8 6"/>
  <path d="M62 28l-8-6"/>
  <circle cx="46" cy="16" r="3" fill="currentColor" stroke="none"/>
  <circle cx="62" cy="28" r="3" fill="currentColor" stroke="none"/>
</g>
SVG),
            ],
            'body' => [
                'key' => 'body',
                'label' => 'Trattamenti corpo',
                'keywords' => ['corpo', 'sedute', 'dimagr', 'massag', 'estetica', 'trattamento', 'cellulite'],
                'svg' => $this->wrap('body', <<<'SVG'
<defs>
  <linearGradient id="body-g" x1="16" y1="16" x2="64" y2="64" gradientUnits="userSpaceOnUse">
    <stop stop-color="currentColor" stop-opacity="0.3"/>
    <stop offset="1" stop-color="currentColor" stop-opacity="0.06"/>
  </linearGradient>
</defs>
<circle cx="40" cy="40" r="35" fill="url(#body-g)"/>
<ellipse cx="40" cy="26" rx="8" ry="10" stroke="currentColor" stroke-width="2"/>
<path d="M40 36v22" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
<path d="M28 50c5-7 10-9 12-9s7 2 12 9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
<path d="M14 34c8-5 18-5 26 0M14 42c8 5 18 5 26 0" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" opacity="0.35"/>
<path d="M54 22c4 3 6 7 6 12s-2 9-6 12" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" opacity="0.5"/>
<circle cx="54" cy="22" r="2.5" fill="currentColor" opacity="0.5"/>
<path d="M50 58c2 2 4 3 6 3s4-1 6-3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" opacity="0.45"/>
SVG),
            ],
            'nails' => [
                'key' => 'nails',
                'label' => 'Beauty & nails',
                'keywords' => ['unghie', 'nail', 'manicure', 'pedicure'],
                'svg' => $this->wrap('nails', <<<'SVG'
<defs>
  <linearGradient id="nails-g" x1="20" y1="20" x2="60" y2="60" gradientUnits="userSpaceOnUse">
    <stop stop-color="currentColor" stop-opacity="0.32"/>
    <stop offset="1" stop-color="currentColor" stop-opacity="0.07"/>
  </linearGradient>
</defs>
<circle cx="40" cy="40" r="35" fill="url(#nails-g)"/>
<path d="M22 48c0-12 8-20 18-20s18 8 18 20" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
<path d="M26 38h28" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
<path d="M28 34h5v8h-5zM36 32h5v10h-5zM44 33h5v9h-5zM52 35h5v7h-5z" fill="currentColor" fill-opacity="0.22" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
<path d="M30 52l5-4 5 3 5-5 5 2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" opacity="0.55"/>
<circle cx="58" cy="24" r="2" fill="currentColor" opacity="0.4"/>
<path d="M56 24l4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" opacity="0.4"/>
SVG),
            ],
            'spa' => [
                'key' => 'spa',
                'label' => 'Benessere',
                'keywords' => ['benessere', 'spa', 'relax', 'viso', 'detox', 'rigener'],
                'svg' => $this->wrap('spa', <<<'SVG'
<defs>
  <linearGradient id="spa-g" x1="16" y1="16" x2="64" y2="64" gradientUnits="userSpaceOnUse">
    <stop stop-color="currentColor" stop-opacity="0.28"/>
    <stop offset="1" stop-color="currentColor" stop-opacity="0.06"/>
  </linearGradient>
</defs>
<circle cx="40" cy="40" r="35" fill="url(#spa-g)"/>
<path d="M40 14c-8 0-14 6-14 14 0 5 2 9 6 12v8h16v-8c4-3 6-7 6-12 0-8-6-14-14-14z" stroke="currentColor" stroke-width="2" stroke-linejoin="round" fill="currentColor" fill-opacity="0.1"/>
<path d="M32 44h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
<ellipse cx="24" cy="54" rx="6" ry="3" fill="currentColor" fill-opacity="0.2" stroke="currentColor" stroke-width="1.5"/>
<ellipse cx="40" cy="56" rx="7" ry="3" fill="currentColor" fill-opacity="0.3" stroke="currentColor" stroke-width="1.5"/>
<ellipse cx="56" cy="54" rx="6" ry="3" fill="currentColor" fill-opacity="0.2" stroke="currentColor" stroke-width="1.5"/>
<path d="M40 20v6M36 23h8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" opacity="0.45"/>
<path d="M18 28c2-4 6-6 10-6M62 28c-2-4-6-6-10-6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" opacity="0.3"/>
SVG),
            ],
            'beauty' => [
                'key' => 'beauty',
                'label' => 'Centro bellezza',
                'keywords' => ['bellezza', 'beauty', 'immagine', 'promoz', 'centro'],
                'svg' => $this->wrap('beauty', <<<'SVG'
<defs>
  <linearGradient id="beauty-g" x1="12" y1="12" x2="68" y2="68" gradientUnits="userSpaceOnUse">
    <stop stop-color="currentColor" stop-opacity="0.34"/>
    <stop offset="1" stop-color="currentColor" stop-opacity="0.08"/>
  </linearGradient>
</defs>
<circle cx="40" cy="40" r="35" fill="url(#beauty-g)"/>
<path d="M40 12l6.5 13.2 14.6 2.1-10.6 10.3 2.5 14.5L40 42.8 27 52.1l2.5-14.5L19 27.3l14.6-2.1L40 12z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" fill="currentColor" fill-opacity="0.18"/>
<circle cx="40" cy="40" r="11" stroke="currentColor" stroke-width="2"/>
<path d="M40 33v14M33 40h14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" opacity="0.35"/>
<path d="M16 18l5 5M64 18l-5 5M16 62l5-5M64 62l-5-5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" opacity="0.32"/>
<circle cx="40" cy="40" r="3" fill="currentColor" fill-opacity="0.5"/>
SVG),
            ],
            'mirror' => [
                'key' => 'mirror',
                'label' => 'Stile & immagine',
                'keywords' => ['stile', 'look', 'make', 'trucco'],
                'svg' => $this->wrap('mirror', <<<'SVG'
<defs>
  <linearGradient id="mirror-g" x1="20" y1="16" x2="60" y2="64" gradientUnits="userSpaceOnUse">
    <stop stop-color="currentColor" stop-opacity="0.3"/>
    <stop offset="1" stop-color="currentColor" stop-opacity="0.07"/>
  </linearGradient>
</defs>
<circle cx="40" cy="40" r="35" fill="url(#mirror-g)"/>
<ellipse cx="40" cy="34" rx="15" ry="19" stroke="currentColor" stroke-width="2" fill="currentColor" fill-opacity="0.08"/>
<path d="M25 34h30" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
<path d="M30 58h20" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
<path d="M34 58v5h12v-5" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
<path d="M32 28c3-3 6-4 8-4s5 1 8 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" opacity="0.45"/>
<path d="M52 20l3 3M56 24l-3 3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" opacity="0.4"/>
SVG),
            ],
        ];
    }

    private function wrap(string $id, string $paths): string
    {
        $paths = str_replace('id="'.$id.'-g"', 'id="promo-icon-'.$id.'-g"', $paths);
        $paths = str_replace('url(#'.$id.'-g)', 'url(#promo-icon-'.$id.'-g)', $paths);

        return <<<SVG
<svg class="promo-icon promo-icon--{$id}" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
{$paths}
</svg>
SVG;
    }
}
