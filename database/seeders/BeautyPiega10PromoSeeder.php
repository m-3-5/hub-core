<?php

namespace Database\Seeders;

use App\Models\Promo;
use App\Models\Tenant;
use App\Services\PromoVisualBuilder;
use App\Services\WordPressWebhookDispatcher;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class BeautyPiega10PromoSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'beauty-of-image')->firstOrFail();

        $source = database_path('seeders/assets/beauty-piega-10euro.png');
        $destDir = storage_path('app/public/promos/beauty-of-image');
        $destPath = 'promos/beauty-of-image/piega-10euro.png';

        if (! is_file($source)) {
            $this->command?->error('File volantino mancante: database/seeders/assets/beauty-piega-10euro.png');

            return;
        }

        File::ensureDirectoryExists($destDir);
        File::copy($source, storage_path('app/public/'.$destPath));

        $startsAt = now();
        $endsAt = now()->addDays(10);

        $promo = Promo::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'slug' => 'piega-10euro',
            ],
            [
                'title' => 'Piega a soli 10€',
                'description' => 'Prenditi cura dei tuoi capelli di nuovo da Beauty of Image. Promo piega a soli 10€ — passa in salone o contattaci per prenotare.',
                'offers' => [
                    [
                        'name' => 'Piega',
                        'price' => '10€',
                        'detail' => 'Piega professionale in salone',
                    ],
                ],
                'cta_label' => 'Prenota ora',
                'cta_url' => $tenant->website,
                'image_path' => $destPath,
                'seo_title' => 'Piega 10€ — Beauty of Image Senise',
                'seo_description' => 'Promo piega a soli 10€ da Beauty of Image a Senise. Prenota il tuo appuntamento in salone.',
                'status' => 'published',
                'always_active' => false,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'published_at' => now(),
                'ai_metadata' => [
                    'source' => 'client_flyer',
                    'generated_without_ai' => true,
                ],
            ]
        );

        $absolute = storage_path('app/public/'.$destPath);
        $variants = app(PromoVisualBuilder::class)->build($tenant, $promo, $destPath, $absolute);
        $promo->update(['image_variants' => $variants]);

        app(WordPressWebhookDispatcher::class)->promoPublished($tenant, $promo->fresh());

        $this->command?->info('Promo piega-10euro creata/aggiornata (attiva 10 giorni, poi torna la promo precedente).');
        $this->command?->line('Scadenza: '.$endsAt->format('d/m/Y H:i'));
        $this->command?->line('Landing: '.route('promo.show', [$tenant, $promo]));
    }
}
