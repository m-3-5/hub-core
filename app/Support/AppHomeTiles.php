<?php

namespace App\Support;

use App\Models\Tenant;

/**
 * Tessere della home dell'app (griglia «tutti i servizi»): solo icona e nome.
 * Il sito web non è nella griglia: ha il suo riquadro grande in cima («Sito web a 1 €»).
 * L'abbonamento non è nella griglia: i costi stanno in pagine a parte.
 */
class AppHomeTiles
{
    /** chiave modulo => [icona, colore] */
    public const META = [
        'promo' => ['promo', '#8b5cf6'], 'services' => ['services', '#ec4899'], 'products' => ['products', '#6366f1'],
        'quotes' => ['quotes', '#0ea5e9'], 'shop' => ['orders', '#6366f1'], 'agenda' => ['calendar', '#14b8a6'],
        'rentals' => ['home', '#14b8a6'], 'website' => ['globe', '#8b5cf6'], 'classifieds' => ['classifieds', '#f59e0b'],
        'giftcard' => ['gift', '#ef4444'], 'loyalty' => ['card', '#0ea5e9'], 'billing' => ['card', '#64748b'],
    ];

    private const NOT_IN_GRID = ['website', 'billing'];

    public function __construct(private HubModules $modules) {}

    /** Tessere per un'attività: prima quelle che si usano già, poi quelle in arrivo o da sbloccare. */
    public function forTenant(Tenant $tenant): array
    {
        return collect($this->modules->forTenant($tenant))
            ->filter(fn (array $m) => $m['fits_type'] && ! in_array($m['key'], self::NOT_IN_GRID, true))
            ->map(fn (array $m) => $this->tile($m, [
                'url' => $m['active'] ? $m['url'] : (! empty($m['badge']) ? route('admin.billing.show', $tenant) : null),
                'tag' => $m['active'] ? null : ($m['badge'] ?? 'Presto'),
            ]))
            ->sortBy(fn (array $t) => $t['tag'] ? 1 : 0)
            ->values()
            ->all();
    }

    /** Tessere per chi apre l'app senza account: i servizi pronti si provano subito da ospite. */
    public function forGuest(): array
    {
        return collect(config('hub.modules'))
            ->reject(fn (array $m) => in_array($m['key'], self::NOT_IN_GRID, true))
            ->map(fn (array $m) => $this->tile($m, [
                'guest' => (bool) $m['active'],
                'tag' => $m['active'] ? null : 'Presto',
            ]))
            ->sortBy(fn (array $t) => $t['tag'] ? 1 : 0)
            ->values()
            ->all();
    }

    /** Il modulo esiste ed è pronto (usato per «inizia da ospite e vai dritto a…»). */
    public static function startableKeys(): array
    {
        return collect(config('hub.modules'))
            ->filter(fn (array $m) => $m['active'] && ! in_array($m['key'], self::NOT_IN_GRID, true))
            ->pluck('key')
            ->all();
    }

    private function tile(array $module, array $extra): array
    {
        [$icon, $color] = self::META[$module['key']] ?? ['promo', '#6366f1'];

        return array_merge([
            'key' => $module['key'],
            'label' => $module['label'],
            'icon' => $icon,
            'color' => $color,
            'url' => null,
            'tag' => null,
            'guest' => false,
        ], $extra);
    }
}
