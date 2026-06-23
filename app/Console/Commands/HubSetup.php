<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class HubSetup extends Command
{
    protected $signature = 'hub:setup';

    protected $description = 'Migration, seed utenti Hub e pulizia cache';

    public function handle(): int
    {
        $this->call('migrate', ['--force' => true]);
        $this->call('db:seed', ['--force' => true]);
        $this->call('config:clear');
        $this->call('route:clear');
        $this->call('view:clear');
        $this->call('cache:clear');

        $this->newLine();
        $this->info('Setup completato.');
        $this->line('Login: http://hub-core.test/admin/login');
        $this->line('Password default: '.config('hub.default_password'));

        return self::SUCCESS;
    }
}
