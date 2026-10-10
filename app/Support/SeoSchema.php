<?php

namespace App\Support;

use App\Models\ClassifiedAd;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use M35\HubPayments\Models\PayableService;

/**
 * Dati strutturati (schema.org, JSON-LD) per le pagine pubbliche: aiutano Google e le ricerche con IA
 * a capire chi siamo, cosa vendiamo e a che prezzo. Prodotti dal PHP (non dal Blade) per evitare
 * che parole come «@context» vengano scambiate per comandi di Blade.
 */
class SeoSchema
{
    /** @param  array<string, mixed>  $graph */
    private static function json(array $graph): string
    {
        return json_encode(['@'.'context' => 'https://schema.org', '@graph' => array_values($graph)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
    }

    /** Organizzazione M 3.5 / Hub Core, riusata nelle pagine che la citano. */
    public static function organization(): array
    {
        $group = config('landing.facebook_group.url');

        return array_filter([
            '@type' => 'Organization',
            '@id' => url('/').'#organizzazione',
            'name' => 'M 3.5 S.R.L.',
            'alternateName' => 'Hub Core',
            'url' => url('/'),
            'logo' => asset('images/icon-192.png'),
            'description' => 'M 3.5 sviluppa Hub Core, la piattaforma per promozioni, servizi, prodotti e pagamenti online di aziende, professionisti e privati, e realizza siti web e app a Corigliano-Rossano e in Calabria.',
            'areaServed' => ['@type' => 'Place', 'name' => config('landing.city')],
            'sameAs' => $group ? [$group] : null,
        ]);
    }

    public static function home(): string
    {
        return self::json([
            self::organization(),
            [
                '@type' => 'WebSite',
                '@id' => url('/').'#sito',
                'url' => url('/'),
                'name' => 'Hub Core',
                'inLanguage' => 'it-IT',
                'publisher' => ['@id' => url('/').'#organizzazione'],
            ],
            [
                '@type' => 'SoftwareApplication',
                'name' => 'Hub Core',
                'applicationCategory' => 'BusinessApplication',
                'operatingSystem' => 'Web, smartphone',
                'url' => url('/'),
                'description' => 'Crea promo, vendi servizi e prodotti con pagamenti online e pubblica tutto sul tuo sito, da smartphone.',
                'offers' => [
                    '@type' => 'Offer',
                    'price' => (string) config('services.hub_billing.monthly_price_eur', 29),
                    'priceCurrency' => 'EUR',
                    'url' => route('pricing.show'),
                ],
                'publisher' => ['@id' => url('/').'#organizzazione'],
            ],
        ]);
    }

    /** @param  Collection<int, \App\Models\Promo>  $promos */
    public static function promoList(Collection $promos, string $name, string $url): string
    {
        return self::json([self::itemList($name, $url, $promos->map(fn ($promo) => [
            'url' => $promo->publicUrl(),
            'name' => $promo->title,
            'image' => $promo->imageUrl(),
        ])->all())]);
    }

    /** Servizi e prodotti di un'azienda. */
    public static function catalog(Tenant $tenant, Collection $services, Collection $products): string
    {
        $items = $services->concat($products)->map(fn (PayableService $item) => [
            'url' => route('services.public.show', [$tenant, $item]),
            'name' => $item->title,
            'image' => $item->coverImageUrl(),
        ])->all();

        return self::json([self::business($tenant), self::itemList('Servizi e prodotti — '.$tenant->name, route('services.public.archive', $tenant), $items)]);
    }

    /** @param  Collection<int, ClassifiedAd>  $ads */
    public static function classifiedBoard(?Tenant $tenant, Collection $ads): string
    {
        $url = $tenant ? route('classifieds.tenant-board', $tenant) : route('classifieds.board');

        return self::json([self::itemList($tenant ? 'Annunci — '.$tenant->name : 'Annunci immobili', $url, $ads->map(fn (ClassifiedAd $ad) => [
            'url' => $ad->publicUrl(),
            'name' => $ad->title,
            'image' => $ad->imageUrls()[0] ?? null,
        ])->all())]);
    }

    public static function classifiedAd(Tenant $tenant, ClassifiedAd $ad): string
    {
        $price = $ad->price !== null ? number_format((float) $ad->price, 2, '.', '') : null;

        return self::json([array_filter([
            '@type' => 'RealEstateListing',
            'name' => $ad->title,
            'url' => $ad->publicUrl(),
            'description' => mb_substr(trim(strip_tags($ad->description)), 0, 300),
            'image' => array_slice($ad->imageUrls(), 0, 4) ?: null,
            'datePosted' => $ad->published_at?->toDateString(),
            'about' => ['@type' => 'Place', 'name' => $ad->zone],
            'offers' => $price ? ['@type' => 'Offer', 'price' => $price, 'priceCurrency' => 'EUR', 'category' => $ad->categoryLabel()] : null,
            'provider' => self::business($tenant),
        ])]);
    }

    /** Attività dell'azienda (non quella di M 3.5). */
    private static function business(Tenant $tenant): array
    {
        return array_filter([
            '@type' => 'LocalBusiness',
            'name' => $tenant->name,
            'url' => $tenant->website ?: null,
        ]);
    }

    /**
     * @param  array<int, array{url: string, name: string, image?: ?string}>  $items
     * @return array<string, mixed>
     */
    private static function itemList(string $name, string $url, array $items): array
    {
        return [
            '@type' => 'CollectionPage',
            'name' => $name,
            'url' => $url,
            'mainEntity' => [
                '@type' => 'ItemList',
                'itemListElement' => collect($items)->values()->map(fn (array $item, int $i) => [
                    '@type' => 'ListItem',
                    'position' => $i + 1,
                    'url' => $item['url'],
                    'name' => $item['name'],
                    'image' => $item['image'] ?? null,
                ])->map(fn (array $row) => array_filter($row))->all(),
            ],
        ];
    }
}
