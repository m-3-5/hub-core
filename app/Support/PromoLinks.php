<?php

namespace App\Support;

use App\Models\Promo;
use App\Models\Tenant;

class PromoLinks
{
    /** @return array<int, array{key: string, label: string, url: string}> */
    public static function forPromo(Tenant $tenant, Promo $promo): array
    {
        $links = [
            [
                'key' => 'visit',
                'label' => 'Visita la promo',
                'url' => $promo->publicUrl(),
            ],
            [
                'key' => 'all_promos',
                'label' => 'Tutte le promo',
                'url' => self::promosPageUrl($tenant),
            ],
        ];

        $whatsapp = self::whatsappUrl($tenant, $promo);
        if ($whatsapp) {
            $links[] = [
                'key' => 'whatsapp',
                'label' => 'Contattaci su WhatsApp',
                'url' => $whatsapp,
            ];
        }

        return $links;
    }

    /** CTA per la landing promo (utente già sulla pagina). */
    /** @return array<int, array{key: string, label: string, url: string|null, disabled?: bool}> */
    public static function forLanding(Tenant $tenant, Promo $promo): array
    {
        $links = [];

        $whatsapp = self::whatsappUrl($tenant, $promo);
        if ($whatsapp) {
            $links[] = [
                'key' => 'whatsapp',
                'label' => 'Contattaci ora',
                'url' => $whatsapp,
            ];
        }

        $bookingUrl = ($tenant->settings ?? [])['booking_url'] ?? null;
        if ($bookingUrl) {
            $links[] = [
                'key' => 'book',
                'label' => 'Prenota ora',
                'url' => $bookingUrl,
            ];
        } else {
            $links[] = [
                'key' => 'book',
                'label' => 'Prenota ora',
                'url' => null,
                'disabled' => true,
            ];
        }

        $links[] = [
            'key' => 'all_promos',
            'label' => 'Tutte le promo',
            'url' => self::promosPageUrl($tenant),
        ];

        if ($tenant->website) {
            $links[] = [
                'key' => 'website',
                'label' => 'Torna al sito web',
                'url' => $tenant->website,
            ];
        }

        return $links;
    }

    public static function promosPageUrl(Tenant $tenant): string
    {
        $settings = $tenant->settings ?? [];

        if (! empty($settings['promos_page_url'])) {
            return $settings['promos_page_url'];
        }

        return rtrim($tenant->website ?? '', '/').'/promozioni';
    }

    public static function whatsappUrl(Tenant $tenant, Promo $promo): ?string
    {
        $number = self::whatsappNumber($tenant);

        if (! $number) {
            return null;
        }

        return 'https://wa.me/'.$number.'?text='.rawurlencode(self::whatsappMessage($tenant, $promo));
    }

    public static function whatsappMessage(Tenant $tenant, Promo $promo): string
    {
        $lines = [
            'Ciao '.$tenant->name.'!',
            'Vorrei informazioni sulla promozione: «'.$promo->title.'».',
        ];

        $offers = $promo->offers ?? [];
        if ($offers !== []) {
            $names = collect($offers)
                ->map(function ($offer) {
                    $line = $offer['name'] ?? '';
                    if (! empty($offer['price'])) {
                        $line .= ' — '.$offer['price'];
                    }

                    return trim($line);
                })
                ->filter()
                ->take(3)
                ->implode(', ');

            if ($names !== '') {
                $lines[] = 'Mi interessa: '.$names.'.';
            }
        }

        $lines[] = 'Grazie!';

        return implode("\n", $lines);
    }

    public static function whatsappNumber(Tenant $tenant): ?string
    {
        $settings = $tenant->settings ?? [];

        if (! empty($settings['whatsapp'])) {
            return self::normalizePhone((string) $settings['whatsapp']);
        }

        if (empty($tenant->phone)) {
            return null;
        }

        // Preferisce numeri mobile (3xx) se presenti nella stringa telefono.
        if (preg_match_all('/(?:\+39\s*)?(3\d{2}[\s.-]?\d{6,7})/', $tenant->phone, $matches)) {
            return self::normalizePhone(end($matches[0]));
        }

        if (preg_match('/(?:\+39\s*)?(\d{9,11})/', $tenant->phone, $match)) {
            return self::normalizePhone($match[0]);
        }

        return null;
    }

    private static function normalizePhone(string $raw): string
    {
        $digits = preg_replace('/\D/', '', $raw) ?? '';

        if (str_starts_with($digits, '39') && strlen($digits) >= 11) {
            return $digits;
        }

        if (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return '39'.$digits;
    }
}
