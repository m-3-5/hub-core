<?php

namespace App\Support;

class MaxIntentMatcher
{
    /** @var array<string, array<int, string>> */
    private const KEYWORDS = [
        'promo' => ['promo', 'sconto', 'offerta', 'volantino', 'landing'],
        'services' => ['servizi', 'servizio', 'pagamento', 'link di pagamento', 'trattamento', 'stripe'],
        'shop' => ['vendere', 'vendita', 'negozio', 'prodotti', 'store', 'ecommerce', 'e-commerce'],
        'agenda' => ['prenotazioni', 'prenotazione', 'appuntamenti', 'appuntamento', 'agenda', 'calendario'],
        'rentals' => ['affitti', 'affitto', 'affittacamere', 'bnb', 'b&b', 'vacanza', 'camere'],
        'website' => ['sito web', 'sito internet', 'vetrina online', 'creare un sito', 'sito per'],
        'classifieds' => ['annunci', 'annuncio', 'bakeca', 'marketplace'],
        'giftcard' => ['gift card', 'buoni regalo', 'buono regalo', 'voucher'],
        'loyalty' => ['fedeltà', 'fedelta', 'tessera', 'tessere', 'punti', 'card clienti'],
        'billing' => ['abbonamento', 'fattura', 'pagare hub core', 'demo scaduta'],
    ];

    /**
     * @param  array<int, array{key: string, label: string, emoji: string, active: bool, url: ?string}>  $modules
     * @return array{key: string, label: string, emoji: string, active: bool, url: ?string}|null
     */
    public static function matchModule(string $text, array $modules): ?array
    {
        $haystack = mb_strtolower($text);

        foreach ($modules as $module) {
            foreach (self::KEYWORDS[$module['key']] ?? [] as $keyword) {
                if (str_contains($haystack, $keyword)) {
                    return $module;
                }
            }
        }

        return null;
    }
}
