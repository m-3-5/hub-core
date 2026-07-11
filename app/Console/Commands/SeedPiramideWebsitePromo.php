<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\GeminiSvgFlyerGenerator;
use App\Services\TenantBrandManager;
use App\Support\TenantPromoQuota;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SeedPiramideWebsitePromo extends Command
{
    protected $signature = 'hub:seed-piramide-website-promo';

    protected $description = 'Crea la promo "Crea il tuo nuovo sito web" per il tenant Piramide 35 (bozza, da rivedere e pubblicare a mano) e allinea il brand (colore/font) al sito reale piramide35.com';

    public function handle(GeminiSvgFlyerGenerator $svgFlyerGenerator, TenantBrandManager $brandManager): int
    {
        $tenant = Tenant::where('slug', 'piramide35')->first();

        if (! $tenant) {
            $this->error('Tenant "piramide35" non trovato.');

            return self::FAILURE;
        }

        $this->alignBrand($tenant, $brandManager);

        $slug = 'crea-il-tuo-nuovo-sito-web';
        $existing = $tenant->promos()->where('slug', $slug)->first();

        if ($existing) {
            $metadata = array_merge($existing->ai_metadata ?? [], ['landing_style' => 'agency']);

            $newFlyer = $svgFlyerGenerator->generate(
                $tenant,
                $existing->title,
                'Progettazione siti web professionali — Senise e Roma',
                null,
                'promos/'.$tenant->slug.'/'.Str::uuid(),
            );

            if ($newFlyer) {
                if ($existing->image_path && Storage::disk('public')->exists($existing->image_path)) {
                    Storage::disk('public')->delete($existing->image_path);
                }

                $existing->update([
                    'ai_metadata' => $metadata,
                    'image_path' => $newFlyer['path'],
                ]);
                $this->info('Volantino rigenerato con il nuovo stile grafico.');
            } else {
                $existing->update(['ai_metadata' => $metadata]);
                $this->warn('Non sono riuscito a rigenerare il volantino (Gemini non ha risposto) — ho comunque applicato la landing page dedicata.');
            }

            $this->warn('Questa promo esisteva già per Piramide 35 — non ne creo un\'altra, ma l\'ho aggiornata.');
            $this->info('Vedila qui: '.route('admin.promos.show', [$tenant, $existing]));

            return self::SUCCESS;
        }

        $title = 'Crea il tuo nuovo sito web con M 3.5 S.R.L.';
        $description = 'M 3.5 S.R.L. progetta e realizza siti web moderni, veloci e pensati per far crescere la tua attività online. '
            .'Ci trovi a Senise, in via Soldato Belfi Giuseppe 11 (sede legale), e a Roma presso il Tecnopolo Tiburtino — Press-Oil. '
            .PHP_EOL.PHP_EOL
            .'Un sito web non è una vetrina: è la prima stretta di mano con chi non ti conosce ancora.';

        $flyer = $svgFlyerGenerator->generate(
            $tenant,
            $title,
            'Progettazione siti web professionali — Senise e Roma',
            null,
            'promos/'.$tenant->slug.'/'.Str::uuid(),
        );

        if (! $flyer) {
            $this->error('Non sono riuscito a generare il volantino automaticamente. Riprova più tardi (dipende da Gemini).');

            return self::FAILURE;
        }

        $overQuota = ! TenantPromoQuota::hasIncludedSlot($tenant);

        $promo = $tenant->promos()->create([
            'title' => $title,
            'slug' => $slug,
            'description' => $description,
            'offers' => [
                [
                    'name' => 'Sito Web Professionale',
                    'price' => '',
                    'detail' => 'Design moderno, veloce, ottimizzato per Google e per i social',
                ],
            ],
            'cta_label' => 'Richiedi un preventivo gratuito',
            'cta_url' => $tenant->website,
            'image_path' => $flyer['path'],
            'seo_title' => 'Crea il tuo sito web — M 3.5 S.R.L. | Piramide 35',
            'seo_description' => 'Siti web professionali a Senise e Roma. M 3.5 S.R.L. progetta il tuo sito su misura, veloce e ottimizzato.',
            'status' => 'draft',
            'always_active' => true,
            'published_at' => null,
            'ai_metadata' => [
                'promo_source' => 'svg',
                'seeded_via' => 'hub:seed-piramide-website-promo',
                'landing_style' => 'agency',
            ],
        ]);

        $this->info('Bozza creata: '.route('admin.promos.show', [$tenant, $promo]));
        $this->info('Vai lì, controlla il volantino, e pubblica quando sei pronto.');

        if ($overQuota) {
            $this->warn('Nota: questo tenant ha superato la quota promo mensile inclusa — verrà conteggiata come extra se pubblicata.');
        }

        return self::SUCCESS;
    }

    private function alignBrand(Tenant $tenant, TenantBrandManager $brandManager): void
    {
        $realColor = '#00d185';

        if ($brandManager->color($tenant) !== $realColor) {
            $brandManager->storeColor($tenant, $realColor);
            $tenant->update(['primary_color' => $realColor]);
            $this->info("Colore brand aggiornato al verde reale di piramide35.com ({$realColor}).");
        }

        if ($brandManager->font($tenant) !== 'digitale') {
            $brandManager->storeFont($tenant, 'digitale');
            $this->info('Font brand aggiornato al preset "Digitale" (Inter + Roboto Mono).');
        }
    }
}
