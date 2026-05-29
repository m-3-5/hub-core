<?php

namespace App\Console\Commands;

use App\Models\Promo;
use App\Services\PromoVisualBuilder;
use Illuminate\Console\Command;

class RebuildPromoVisuals extends Command
{
    protected $signature = 'promo:rebuild-visuals {promo? : Promo ID}';

    protected $description = 'Rigenera SVG e immagine AI per una promo';

    public function handle(PromoVisualBuilder $visuals): int
    {
        $promoId = $this->argument('promo');

        $promos = $promoId
            ? Promo::whereKey($promoId)->get()
            : Promo::all();

        foreach ($promos as $promo) {
            $promo->load('tenant');
            $absolute = storage_path('app/public/'.$promo->image_path);

            $promo->update([
                'image_variants' => $visuals->build($promo->tenant, $promo, $promo->image_path, $absolute),
            ]);

            $this->info("Visual aggiornati per promo #{$promo->id}: {$promo->title}");
        }

        return self::SUCCESS;
    }
}
