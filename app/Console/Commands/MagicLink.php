<?php

namespace App\Console\Commands;

use App\Http\Controllers\MagicLoginController;
use App\Models\Tenant;
use Illuminate\Console\Command;

class MagicLink extends Command
{
    protected $signature = 'hub:magic-link {tenant : Slug dell\'azienda (es. sonia)} {--email= : Utente specifico dell\'azienda} {--days=7 : Giorni di validità}';

    protected $description = 'Genera un link di accesso senza password (a scadenza) per un utente di un\'azienda';

    public function handle(): int
    {
        $tenant = Tenant::where('slug', $this->argument('tenant'))->first();

        if (! $tenant) {
            $this->error('Azienda non trovata: '.$this->argument('tenant'));

            return self::FAILURE;
        }

        $users = $tenant->users()->where('is_super_admin', false)->get();

        if ($this->option('email')) {
            $users = $users->where('email', $this->option('email'));
        }

        if ($users->isEmpty()) {
            $this->error('Nessun utente collegato a «'.$tenant->name.'» (non super admin)'
                .($this->option('email') ? ' con quell\'email.' : '. Collega prima un utente all\'azienda.'));

            return self::FAILURE;
        }

        if ($users->count() > 1 && ! $this->option('email')) {
            $this->warn('Più utenti collegati, uso il primo. Per sceglierne uno: --email=...');
            foreach ($users as $candidate) {
                $this->line(' - '.$candidate->email);
            }
        }

        $user = $users->first();
        $days = max(1, min(30, (int) $this->option('days')));

        $this->info('Link per '.$user->name.' <'.$user->email.'> — valido '.$days.' giorni:');
        $this->line(MagicLoginController::makeUrl($user, $tenant, $days));

        return self::SUCCESS;
    }
}
