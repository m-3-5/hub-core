<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\GeminiThemeIconGenerator;
use Illuminate\Console\Command;

class GenerateGeminiIcons extends Command
{
    protected $signature = 'icons:generate-gemini
                            {tenant : Slug tenant (es. beauty-of-image)}
                            {--force : Rigenera anche icone già presenti}
                            {--keys= : Chiavi separate da virgola (hair,body,spa,nails,beauty)}';

    protected $description = 'Genera icone SVG beauty con Gemini (silhouette parrucchiere, corpo, viso…)';

    public function handle(GeminiThemeIconGenerator $generator): int
    {
        $tenant = Tenant::where('slug', $this->argument('tenant'))->firstOrFail();

        $keys = $this->option('keys')
            ? array_values(array_filter(array_map('trim', explode(',', $this->option('keys')))))
            : ['hair', 'body', 'nails', 'spa', 'beauty', 'mirror'];

        $this->info("Generazione icone Gemini per {$tenant->name}…");

        foreach ($keys as $key) {
            $this->line(" → {$key}");
        }

        $count = $generator->ensure($tenant, $keys, (bool) $this->option('force'));

        $this->info("Completato: {$count} icona/e generate o aggiornate.");
        $this->line('Percorso: storage/app/public/brand-icons/ai/'.$tenant->slug.'/');

        return self::SUCCESS;
    }
}
