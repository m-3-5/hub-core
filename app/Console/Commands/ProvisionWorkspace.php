<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\TenantWorkspaceManager;
use Illuminate\Console\Command;

class ProvisionWorkspace extends Command
{
    protected $signature = 'hub:provision-workspace
                            {tenant : Slug tenant (es. beauty-of-image)}
                            {--skip-migrate : Crea solo il database}';

    protected $description = 'Crea database workspace dedicato per tenant premium e applica migration';

    public function handle(TenantWorkspaceManager $workspaces): int
    {
        $tenant = Tenant::where('slug', $this->argument('tenant'))->firstOrFail();

        if ($tenant->plan !== 'dedicated') {
            $this->warn("Tenant {$tenant->slug} non è plan=dedicated. Procedo comunque.");
        }

        if (! $tenant->workspace_database) {
            $this->error('workspace_database non configurato sul tenant. Aggiorna il seeder o il record tenants.');

            return self::FAILURE;
        }

        $this->info("Provisioning workspace per {$tenant->name}…");
        $this->line("Database: {$tenant->workspace_database}");

        $workspaces->createDatabase($tenant);
        $this->info('Database creato o già esistente.');

        if (! $this->option('skip-migrate')) {
            $connection = $workspaces->registerConnection($tenant);
            $workspaces->migrate($tenant);
            $this->info("Migration workspace eseguite su connessione [{$connection}].");
        }

        if (! $tenant->workspace_url) {
            $tenant->workspace_url = $workspaces->defaultWorkspaceUrl($tenant);
            $tenant->save();
            $this->line("workspace_url impostato: {$tenant->workspace_url}");
        }

        $this->newLine();
        $this->info('Workspace pronto.');
        $this->line('Prossimo passo: scaffold repo Laravel dedicato e export dati da hub.');

        return self::SUCCESS;
    }
}
