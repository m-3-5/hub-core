<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;

class SetTenantTagline extends Command
{
    protected $signature = 'hub:set-tenant-tagline {tenant} {tagline}';

    protected $description = 'Imposta la frase/slogan (tenant.settings.tagline) mostrata sulle promo pubbliche di un tenant';

    public function handle(): int
    {
        $tenant = Tenant::where('slug', $this->argument('tenant'))->first();

        if (! $tenant) {
            $this->error('Tenant "'.$this->argument('tenant').'" non trovato.');

            return self::FAILURE;
        }

        $settings = $tenant->settings ?? [];
        $settings['tagline'] = $this->argument('tagline');
        $tenant->update(['settings' => $settings]);

        $this->info("Tagline impostata per {$tenant->name}: \"{$this->argument('tagline')}\"");

        return self::SUCCESS;
    }
}
