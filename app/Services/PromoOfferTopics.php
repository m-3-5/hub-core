<?php

namespace App\Services;

class PromoOfferTopics
{
    /** @return array<string, array{key: string, label: string, keywords: array<string>}> */
    public static function catalog(): array
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
        ];
    }

    public static function keyForOffer(array $offer): string
    {
        $text = strtolower(($offer['name'] ?? '').' '.($offer['detail'] ?? ''));

        foreach (self::catalog() as $item) {
            foreach ($item['keywords'] as $keyword) {
                if (str_contains($text, $keyword)) {
                    return $item['key'];
                }
            }
        }

        return 'beauty';
    }

    public static function labelForOffer(array $offer): string
    {
        return $offer['name'] ?? self::catalog()[self::keyForOffer($offer)]['label'];
    }

    /** @return array<int, array{key: string, label: string}> */
    public static function topicsForPromo(array $offers, ?string $description = null): array
    {
        $matched = [];

        foreach ($offers as $i => $offer) {
            $key = self::keyForOffer($offer);
            $matched["offer-{$i}"] = [
                'key' => $key,
                'label' => self::labelForOffer($offer),
                'topic' => self::catalog()[$key]['label'],
            ];
        }

        if ($matched === [] && $description) {
            $matched['beauty'] = [
                'key' => 'beauty',
                'label' => 'Centro bellezza',
                'topic' => 'Centro bellezza',
            ];
        }

        return $matched;
    }
}
