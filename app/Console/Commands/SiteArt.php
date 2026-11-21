<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use M35\HubSitebuilder\Models\GeneratedSite;
use M35\HubSitebuilder\Services\SiteBuilderHeroGenerator;
use M35\HubSitebuilder\Services\SiteContent;

/** Crea il disegno moderno in alto (SVG con l'IA, gratis) per i siti già online. */
class SiteArt extends Command
{
    protected $signature = 'hub:site-art {slug? : un solo sito} {--all : tutti i siti online} {--force : rifallo anche se c\'è già}';

    protected $description = 'Disegno moderno a tema nella parte in alto dei siti creati con l\'IA';

    public function handle(SiteBuilderHeroGenerator $generator): int
    {
        $sites = GeneratedSite::query()->where('status', 'published')
            ->when($this->argument('slug'), fn ($q, $slug) => $q->whereIn('tenant_id', Tenant::where('slug', $slug)->pluck('id')))
            ->get();

        if (! $this->argument('slug') && ! $this->option('all')) {
            $this->warn('Indica uno slug oppure --all.');

            return self::FAILURE;
        }

        foreach ($sites as $site) {
            $tenant = $site->tenant ?? Tenant::find($site->tenant_id);
            if (! $tenant || (! $this->option('force') && $site->hero_svg_path && str_contains($site->hero_svg_path, '/art-'))) {
                continue;
            }

            $path = $generator->illustration($tenant, $site->answers ?? [], SiteContent::heroDir($tenant));
            if ($path) {
                SiteContent::replaceArt($site, $path);
                $this->info("{$tenant->slug}: fatto");
            } else {
                $this->error("{$tenant->slug}: l'IA non ha risposto, riprova");
            }
        }

        return self::SUCCESS;
    }
}
