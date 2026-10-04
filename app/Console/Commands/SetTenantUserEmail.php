<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SetTenantUserEmail extends Command
{
    protected $signature = 'hub:tenant-user {tenant : Slug dell\'azienda} {email : Email reale che deve ricevere i messaggi}';

    protected $description = 'Collega un\'email reale a un\'azienda: sostituisce l\'email segnaposto di un account ospite, altrimenti aggiunge l\'utente';

    public function handle(): int
    {
        $tenant = Tenant::where('slug', $this->argument('tenant'))->first();
        $email = Str::lower(trim($this->argument('email')));

        if (! $tenant) {
            $this->error('Azienda non trovata.');

            return self::FAILURE;
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Email non valida.');

            return self::FAILURE;
        }

        $existing = User::where('email', $email)->first();

        if ($existing?->isSuperAdmin()) {
            $this->error('Quell\'email è di un super admin: non la collego a un\'azienda.');

            return self::FAILURE;
        }

        $members = $tenant->users()->where('is_super_admin', false)->get();
        $guests = $members->filter(fn (User $u) => str_ends_with($u->email, '@guest.hub-core.local'));

        if ($existing) {
            $tenant->users()->syncWithoutDetaching([$existing->id => ['role' => 'admin']]);
            $this->info("Utente esistente {$email} collegato a «{$tenant->name}».");
        } elseif ($guests->count() === 1) {
            $guest = $guests->first();
            $guest->update(['email' => $email]);
            $this->info("Email segnaposto dell'account ospite #{$guest->id} sostituita con {$email}.");
        } else {
            $user = User::create(['name' => $tenant->name, 'email' => $email, 'password' => Str::random(40)]);
            $tenant->users()->attach($user->id, ['role' => 'admin']);
            $this->info("Nuovo utente {$email} creato (password casuale) e collegato a «{$tenant->name}».");
        }

        $this->line('Utenti ora collegati:');
        foreach ($tenant->users()->get() as $u) {
            $this->line(' - '.$u->email.($u->isSuperAdmin() ? ' (super admin)' : ''));
        }

        return self::SUCCESS;
    }
}
