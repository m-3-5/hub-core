<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;

class EnableAutoSiteSync extends Command
{
    protected $signature = 'hub:enable-auto-site-sync {tenant=beauty-of-image}';

    protected $description = 'Abilita il sync automatico verso il sito WordPress del tenant (oggi solo Beauty of Image ce l\'ha già collegato)';

    public function handle(): int
    {
        $tenant = Tenant::where('slug', $this->argument('tenant'))->first();

        if (! $tenant) {
            $this->error('Tenant "'.$this->argument('tenant').'" non trovato.');

            return self::FAILURE;
        }

        $settings = $tenant->settings ?? [];
        $settings['auto_site_sync'] = true;
        $tenant->update(['settings' => $settings]);

        $this->info("Sync automatico verso il sito abilitato per {$tenant->name}.");

        return self::SUCCESS;
    }
}
