<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\GeminiPromoGenerator;
use App\Services\PromoVisualBuilder;
use App\Services\TenantBrandManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CreatePromoFromFile extends Command
{
    protected $signature = 'promo:create-from-file
                            {tenant : Slug tenant}
                            {image : Percorso immagine promo assoluto o relativo a storage/app/public}
                            {--slug= : Slug promo personalizzato}
                            {--skip-ai : Solo upload, testi predefiniti}';

    protected $description = 'Crea promo bozza da file immagine (volantino)';

    public function handle(
        GeminiPromoGenerator $generator,
        PromoVisualBuilder $visuals,
    ): int {
        $tenant = Tenant::where('slug', $this->argument('tenant'))->firstOrFail();
        $imageArg = $this->argument('image');

        $source = is_file($imageArg)
            ? $imageArg
            : storage_path('app/public/'.ltrim($imageArg, '/'));

        if (! is_file($source)) {
            $this->error("File non trovato: {$source}");

            return self::FAILURE;
        }

        $slugBase = $this->option('slug') ?: 'promo-'.now()->format('Ymd-His');
        $slug = $slugBase;
        $i = 1;
        while ($tenant->promos()->where('slug', $slug)->exists()) {
            $slug = $slugBase.'-'.$i++;
        }

        $dir = "promos/{$tenant->slug}";
        $filename = $slug.'.'.strtolower(pathinfo($source, PATHINFO_EXTENSION) ?: 'png');
        $path = $dir.'/'.$filename;

        File::ensureDirectoryExists(storage_path('app/public/'.$dir));
        File::copy($source, storage_path('app/public/'.$path));

        $absolutePath = storage_path('app/public/'.$path);
        $mime = mime_content_type($absolutePath) ?: 'image/png';

        if ($this->option('skip-ai')) {
            $generated = GeminiPromoGenerator::fallbackData($tenant->name);
        } else {
            $this->info('Analisi volantino con Gemini…');
            try {
                $generated = $generator->generateFromImage($absolutePath, $mime);
            } catch (\Throwable $e) {
                $this->warn('IA testi: '.$e->getMessage().' — uso testi predefiniti.');
                $generated = GeminiPromoGenerator::fallbackData($tenant->name);
            }
        }

        $promo = $tenant->promos()->create([
            'title' => $generated['title'] ?? 'Fondamenta Digitali',
            'slug' => $slug,
            'description' => $generated['description'] ?? null,
            'offers' => $generated['offers'] ?? [],
            'cta_label' => $generated['cta_label'] ?? 'Richiedi voucher',
            'cta_url' => $tenant->website,
            'image_path' => $path,
            'seo_title' => $generated['seo_title'] ?? null,
            'seo_description' => $generated['seo_description'] ?? null,
            'status' => 'draft',
            'always_active' => true,
            'published_at' => null,
            'ai_metadata' => $generated,
        ]);

        $this->info('Generazione visual (hero/decor — può saltare se quota immagini esaurita)…');
        $promo->update([
            'image_variants' => $visuals->build($tenant, $promo, $path, $absolutePath),
        ]);

        $this->newLine();
        $this->info('Promo creata: '.$promo->title);
        $this->line('Anteprima: '.route('admin.promos.preview', [$tenant, $promo]));
        $this->line('Gestisci: '.route('admin.promos.show', [$tenant, $promo]));

        return self::SUCCESS;
    }
}
