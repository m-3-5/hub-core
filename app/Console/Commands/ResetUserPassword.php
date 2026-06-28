<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ResetUserPassword extends Command
{
    protected $signature = 'hub:reset-password
                            {email : Email dell\'utente admin}
                            {--password= : Nuova password (se omessa, ne genera una casuale)}
                            {--send-link : Invia link via email invece di impostare la password direttamente}';

    protected $description = 'Reimposta la password di un utente (emergenza SSH, senza email)';

    public function handle(): int
    {
        $email = $this->argument('email');

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("Nessun utente con email {$email}");

            return self::FAILURE;
        }

        if ($this->option('send-link')) {
            return $this->call('hub:send-access-link', ['email' => $email]);
        }

        $password = $this->option('password') ?: Str::password(16);

        $user->forceFill([
            'password' => Hash::make($password),
            'remember_token' => Str::random(60),
        ])->save();

        $this->info("Password aggiornata per {$user->name} <{$user->email}>.");
        $this->newLine();
        $this->line('Nuova password: '.$password);
        $this->warn('Copiala ora: non verrà mostrata di nuovo.');

        return self::SUCCESS;
    }
}
