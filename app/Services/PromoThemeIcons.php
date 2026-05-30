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
<path d="M41 6c16 2 28 14 29 30 1 18-12 34-28 35-16 1-30-14-31-32C10 22 24 7 41 6z" fill="currentColor" fill-opacity="0.11"/>
<ellipse cx="40" cy="33" rx="10" ry="11" fill="currentColor" fill-opacity="0.2" stroke="currentColor" stroke-width="1.2"/>
<path d="M27 30c-5 8-6 20-2 32" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" fill="none" opacity="0.85"/>
<path d="M53 30c5 8 6 20 2 32" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" fill="none" opacity="0.85"/>
<path d="M26 34c3-10 9-16 14-18M54 34c-3-10-9-16-14-18" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" fill="none" opacity="0.55"/>
<path d="M28 24c5-6 12-9 20-8 7 1 13 5 16 12" fill="currentColor" fill-opacity="0.14" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
<path d="M36 38c1 1 3 1 4 0M40 38c1 1 3 1 4 0" stroke="currentColor" stroke-width="1" stroke-linecap="round" opacity="0.45"/>
<path d="M37 42c2 2 5 2 7 0" stroke="currentColor" stroke-width="1" stroke-linecap="round" opacity="0.4"/>
SVG),
            ],
            'body' => [
                'key' => 'body',
                'label' => 'Trattamenti corpo',
                'keywords' => ['corpo', 'sedute', 'dimagr', 'massag', 'estetica', 'trattamento', 'cellulite'],
                'svg' => $this->wrap('body', <<<'SVG'
<path d="M39 7c14 1 26 12 28 27 2 16-10 31-26 33-15 2-29-12-30-28C10 23 23 8 39 7z" fill="currentColor" fill-opacity="0.11"/>
<circle cx="40" cy="22" r="7" fill="currentColor" fill-opacity="0.2" stroke="currentColor" stroke-width="1.2"/>
<path d="M40 29c-8 2-12 10-13 20-1 8 2 15 6 19M40 29c8 2 12 10 13 20 1 8-2 15-6 19" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" fill="none"/>
<path d="M32 38c-6 4-9 12-9 20M48 38c6 4 9 12 9 20" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" fill="none" opacity="0.7"/>
<path d="M34 52c2 6 2 12 0 18M46 52c-2 6-2 12 0 18" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" opacity="0.55"/>
<path d="M18 40c6-4 14-4 20 0M62 44c-6 3-12 3-18 0" stroke="currentColor" stroke-width="1" stroke-linecap="round" opacity="0.25"/>
SVG),
            ],
            'nails' => [
                'key' => 'nails',
                'label' => 'Beauty & nails',
                'keywords' => ['unghie', 'nail', 'manicure', 'pedicure'],
                'svg' => $this->wrap('nails', <<<'SVG'
<path d="M40 8c15 1 27 13 28 28 1 17-11 32-27 33-15 1-29-13-30-29C10 24 24 9 40 8z" fill="currentColor" fill-opacity="0.11"/>
<path d="M22 52c0-16 11-26 24-22 11 3 18 14 16 26-2 10-10 16-20 16H28c-10 0-18-9-18-20z" fill="currentColor" fill-opacity="0.16" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/>
<path d="M30 36c0-6 2-10 4-12M36 32c0-8 2-12 4-14M42 33c0-7 2-11 4-13M48 37c0-5 2-8 4-10" stroke="currentColor" stroke-width="1.35" stroke-linecap="round"/>
<path d="M30 34h3v5h-3zM36 30h3v7h-3zM42 31h3v6h-3zM48 35h3v4h-3z" fill="currentColor" fill-opacity="0.28" stroke="none" rx="1"/>
<path d="M26 58c3-2 6-3 9-2 3 1 6 0 9-2" stroke="currentColor" stroke-width="1" stroke-linecap="round" opacity="0.35"/>
SVG),
            ],
            'spa' => [
                'key' => 'spa',
                'label' => 'Benessere',
                'keywords' => ['benessere', 'spa', 'relax', 'viso', 'detox', 'rigener'],
                'svg' => $this->wrap('spa', <<<'SVG'
<path d="M42 6c16 2 28 14 29 30 1 18-12 34-28 35-16 1-30-14-31-32C10 22 24 7 42 6z" fill="currentColor" fill-opacity="0.11"/>
<ellipse cx="38" cy="36" rx="13" ry="15" fill="currentColor" fill-opacity="0.18" stroke="currentColor" stroke-width="1.2"/>
<path d="M30 35c2 2 4 2 6 0M40 35c2 2 4 2 6 0" stroke="currentColor" stroke-width="1.15" stroke-linecap="round" opacity="0.65"/>
<path d="M32 44c4 3 8 3 12 0" stroke="currentColor" stroke-width="1.15" stroke-linecap="round" opacity="0.55"/>
<path d="M54 22c-5 5-5 12 0 17 5-5 5-12 0-17z" fill="currentColor" fill-opacity="0.22" stroke="currentColor" stroke-width="1.1"/>
<path d="M54 26v6M51 29h6" stroke="currentColor" stroke-width="0.9" stroke-linecap="round" opacity="0.4"/>
<path d="M16 38c3-3 7-4 10-2M62 42c-3 2-6 2-9 0" stroke="currentColor" stroke-width="1" stroke-linecap="round" opacity="0.2"/>
SVG),
            ],
            'beauty' => [
                'key' => 'beauty',
                'label' => 'Centro bellezza',
                'keywords' => ['bellezza', 'beauty', 'immagine', 'promoz', 'centro'],
                'svg' => $this->wrap('beauty', <<<'SVG'
<path d="M39 7c14 1 26 12 28 27 2 16-10 31-26 33-15 2-29-12-30-28C10 23 23 8 39 7z" fill="currentColor" fill-opacity="0.11"/>
<circle cx="40" cy="40" r="5" fill="currentColor" fill-opacity="0.28"/>
<path d="M40 22c-7 0-10 6-10 11s3 11 10 11 10-6 10-11-3-11-10-11z" fill="currentColor" fill-opacity="0.2" stroke="currentColor" stroke-width="1.1"/>
<path d="M40 53c-9-6-9-18 0-24 9 6 9 18 0 24z" fill="currentColor" fill-opacity="0.18" stroke="currentColor" stroke-width="1.1"/>
<path d="M27 36c-6-4-6-12 0-16M53 36c6-4 6-12 0-16" fill="currentColor" fill-opacity="0.16" stroke="currentColor" stroke-width="1.1"/>
<path d="M40 53c9-6 9-18 0-24" fill="currentColor" fill-opacity="0.14" stroke="currentColor" stroke-width="1.1"/>
<path d="M22 22l3 3M58 22l-3 3M22 58l3-3M58 58l-3-3" stroke="currentColor" stroke-width="1" stroke-linecap="round" opacity="0.22"/>
SVG),
            ],
            'mirror' => [
                'key' => 'mirror',
                'label' => 'Stile & immagine',
                'keywords' => ['stile', 'look', 'make', 'trucco'],
                'svg' => $this->wrap('mirror', <<<'SVG'
<path d="M41 6c16 2 28 14 29 30 1 18-12 34-28 35-16 1-30-14-31-32C10 22 24 7 41 6z" fill="currentColor" fill-opacity="0.11"/>
<ellipse cx="40" cy="34" rx="14" ry="17" fill="currentColor" fill-opacity="0.14" stroke="currentColor" stroke-width="1.2"/>
<ellipse cx="40" cy="34" rx="10" ry="12" fill="currentColor" fill-opacity="0.08"/>
<path d="M34 32c2-2 4-3 6-3s4 1 6 3" stroke="currentColor" stroke-width="1.1" stroke-linecap="round" opacity="0.5"/>
<path d="M33 38c3 2 6 2 9 0" stroke="currentColor" stroke-width="1" stroke-linecap="round" opacity="0.45"/>
<path d="M32 56h16" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
<path d="M36 56v4h8v-4" stroke="currentColor" stroke-width="1.1" stroke-linejoin="round" fill="none"/>
<circle cx="52" cy="22" r="2" fill="currentColor" fill-opacity="0.35"/>
SVG),
            ],
        ];
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
