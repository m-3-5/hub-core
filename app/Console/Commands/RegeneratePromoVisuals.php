<?php

namespace App\Console\Commands;

use App\Models\Promo;
use App\Models\Tenant;
use App\Services\PromoVisualBuilder;
use Illuminate\Console\Command;

class RegeneratePromoVisuals extends Command
{
    protected $signature = 'promo:regenerate-visuals {tenant} {promo? : Slug promo (tutte se omesso)}';

    protected $description = 'Rigenera hero e immagini decorative (IA se abilitata, altrimenti volantino + SVG)';

    public function handle(PromoVisualBuilder $visuals): int
    {
        $tenant = Tenant::where('slug', $this->argument('tenant'))->firstOrFail();
        $slug = $this->argument('promo');

        $query = $tenant->promos();

        if ($slug) {
            $query->where('slug', $slug);
        }

        $promos = $query->get();

        if ($promos->isEmpty()) {
            $this->error('Nessuna promo trovata.');

            return self::FAILURE;
        }

        foreach ($promos as $promo) {
            $this->line("→ {$promo->title}");

            if (! $promo->image_path) {
                $this->warn('  Saltata: nessuna immagine.');

                continue;
            }

            $absolute = storage_path('app/public/'.$promo->image_path);

            $variants = $visuals->build($tenant, $promo, $promo->image_path, $absolute);
            $promo->update(['image_variants' => $variants]);

            $decorCount = count($variants['decor'] ?? []);
            $this->info("  OK — decor: {$decorCount}, hero: ".(isset($variants['hero']) ? 'sì' : 'no'));
        }

        return self::SUCCESS;
    }
}
