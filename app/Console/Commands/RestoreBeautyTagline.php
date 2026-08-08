<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;

class RestoreBeautyTagline extends Command
{
    protected $signature = 'hub:restore-beauty-tagline';

    protected $description = 'Ripristina la tagline "Il tuo corpo, la nostra immagine." per Beauty of Image (nessun argomento, evita problemi di quoting sulla console Plesk)';

    public function handle(): int
    {
        $tenant = Tenant::where('slug', 'beauty-of-image')->first();

        if (! $tenant) {
            $this->error('Tenant "beauty-of-image" non trovato.');

            return self::FAILURE;
        }

        $settings = $tenant->settings ?? [];
        $settings['tagline'] = 'Il tuo corpo, la nostra immagine.';
        $tenant->update(['settings' => $settings]);

        $this->info('Tagline ripristinata per Beauty of Image.');

        return self::SUCCESS;
    }
}
