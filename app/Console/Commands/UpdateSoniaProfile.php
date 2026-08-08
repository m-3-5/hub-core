<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\GeminiSvgFlyerGenerator;
use App\Services\TenantBrandManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UpdateSoniaProfile extends Command
{
    protected $signature = 'hub:update-sonia-profile';

    protected $description = 'Aggiorna il tenant "sonia" a privato + promo lezioni di Matematica e Inglese, con volantino rigenerato a tema';

    public function handle(GeminiSvgFlyerGenerator $svgFlyerGenerator, TenantBrandManager $brandManager): int
    {
        $tenant = Tenant::where('slug', 'sonia')->first();

        if (! $tenant) {
            $this->error('Tenant "sonia" non trovato.');

            return self::FAILURE;
        }

        $tenant->update([
            'type' => 'privato',
            'subscription_status' => 'free',
            'trial_ends_at' => null,
        ]);
        $this->info('Tipo impostato su "privato".');

        $title = 'Lezioni private di Matematica e Inglese con Sonia';
        $description = 'Sonia, laureata in Economia e Commercio, offre lezioni private di Matematica e Inglese '
            .'per studenti di ogni livello. Al momento le lezioni in presenza si tengono a Senise '
            .'(prossimamente anche a Bergamo), ma sono disponibili anche online, ovunque tu sia.'
            .PHP_EOL.PHP_EOL
            .'Scrivimi con il modulo di contatto qui sotto per organizzare la prima lezione.';

        $flyer = $svgFlyerGenerator->generate(
            $tenant,
            $title,
            'Lezioni private, in presenza o online — Matematica e Inglese',
            null,
            'promos/'.$tenant->slug.'/'.Str::uuid(),
        );

        if (! $flyer) {
            $this->error('Non sono riuscito a generare il nuovo volantino (dipende da Gemini). Il resto del profilo è comunque stato aggiornato.');

            return self::FAILURE;
        }

        $slug = 'lezioni-matematica-inglese';
        // Aggiorna la sua promo esistente (qualunque slug abbia) invece di crearne una seconda;
        // il vecchio slug placeholder viene sostituito da quello nuovo a tema lezioni.
        $promo = $tenant->promos()->where('slug', $slug)->first() ?? $tenant->promos()->latest()->first();

        $oldImagePath = $promo?->image_path;
        $oldSvgPath = $promo?->ai_metadata['flyer_svg_path'] ?? null;

        $data = [
            'title' => $title,
            'slug' => $slug,
            'description' => $description,
            'offers' => [
                ['name' => 'Lezioni di Matematica', 'price' => '', 'detail' => 'Aritmetica, algebra, geometria — dalle medie alle superiori'],
                ['name' => 'Lezioni di Inglese', 'price' => '', 'detail' => 'Grammatica, conversazione, preparazione a verifiche ed esami'],
            ],
            'cta_label' => 'Scrivimi per la prima lezione',
            'image_path' => $flyer['path'],
            'seo_title' => 'Lezioni private di Matematica e Inglese — Sonia',
            'seo_description' => 'Lezioni private di Matematica e Inglese con Sonia, laureata in Economia e Commercio. In presenza a Senise o online.',
            'status' => 'published',
            'always_active' => true,
            'starts_at' => null,
            'ends_at' => null,
            'ai_metadata' => [
                'promo_source' => 'svg',
                'seeded_via' => 'hub:update-sonia-profile',
                'flyer_svg_path' => $flyer['svg_path'],
            ],
        ];

        if ($promo) {
            if (! $promo->isPublished()) {
                $data['published_at'] = now();
            }

            $promo->update($data);
            $this->info('Promo esistente aggiornata: '.route('admin.promos.show', [$tenant, $promo]));
        } else {
            $data['published_at'] = now();
            $promo = $tenant->promos()->create($data);
            $this->info('Promo creata e pubblicata: '.route('admin.promos.show', [$tenant, $promo]));
        }

        if ($oldImagePath && $oldImagePath !== $flyer['path'] && Storage::disk('public')->exists($oldImagePath)) {
            Storage::disk('public')->delete($oldImagePath);
        }

        if ($oldSvgPath && $oldSvgPath !== $flyer['svg_path'] && Storage::disk('public')->exists($oldSvgPath)) {
            Storage::disk('public')->delete($oldSvgPath);
        }

        $this->info('Pagina pubblica: '.$promo->publicUrl());

        return self::SUCCESS;
    }
}
