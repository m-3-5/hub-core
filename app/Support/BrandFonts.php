<?php

namespace App\Support;

class BrandFonts
{
    /** @var array<string, array{label: string, description: string, display: string, body: string, google_fonts: string}> */
    public const PRESETS = [
        'elegante' => [
            'label' => 'Elegante',
            'description' => 'Serif raffinato + sans pulito — beauty, benessere, moda',
            'display' => "'Cormorant Garamond', serif",
            'body' => "'Outfit', sans-serif",
            'accent' => "'Great Vibes', cursive",
            'google_fonts' => 'family=Cormorant+Garamond:ital,wght@0,600;0,700&family=Outfit:wght@400;500;600;700&family=Great+Vibes',
        ],
        'moderno' => [
            'label' => 'Moderno',
            'description' => 'Pulito e diretto — servizi, professionisti, negozi',
            'display' => "'Poppins', sans-serif",
            'body' => "'Inter', sans-serif",
            'accent' => "'Poppins', sans-serif",
            'google_fonts' => 'family=Poppins:wght@600;700;800&family=Inter:wght@400;500;600',
        ],
        'amichevole' => [
            'label' => 'Amichevole',
            'description' => 'Morbido e accogliente — attività di quartiere, famiglia',
            'display' => "'Quicksand', sans-serif",
            'body' => "'Nunito', sans-serif",
            'accent' => "'Quicksand', sans-serif",
            'google_fonts' => 'family=Quicksand:wght@600;700&family=Nunito:wght@400;500;600;700',
        ],
        'tech' => [
            'label' => 'Tech',
            'description' => 'Squadrato e sicuro — agenzie, web, consulenza',
            'display' => "'Space Grotesk', sans-serif",
            'body' => "'Inter', sans-serif",
            'accent' => "'Space Grotesk', sans-serif",
            'google_fonts' => 'family=Space+Grotesk:wght@600;700&family=Inter:wght@400;500;600',
        ],
        'classico' => [
            'label' => 'Classico',
            'description' => 'Autorevole e istituzionale — enti, studi, associazioni',
            'display' => "'Playfair Display', serif",
            'body' => "'Source Sans 3', sans-serif",
            'accent' => "'Playfair Display', serif",
            'google_fonts' => 'family=Playfair+Display:wght@600;700&family=Source+Sans+3:wght@400;500;600',
        ],
        'digitale' => [
            'label' => 'Digitale',
            'description' => 'Scuro e da terminale — software house, startup, infrastrutture',
            'display' => "'Inter', sans-serif",
            'body' => "'Inter', sans-serif",
            'accent' => "'Roboto Mono', monospace",
            'google_fonts' => 'family=Inter:wght@400;500;600;700;800&family=Roboto+Mono:wght@500;600;700',
        ],
    ];

    public static function default(): string
    {
        return 'elegante';
    }

    /** @return array{label: string, description: string, display: string, body: string, google_fonts: string} */
    public static function get(string $key): array
    {
        return self::PRESETS[$key] ?? self::PRESETS[self::default()];
    }
}
