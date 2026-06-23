<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Password;

class SendAccessLink extends Command
{
    protected $signature = 'hub:send-access-link {email : Email dell\'utente}';

    protected $description = 'Invia email con link per impostare/reimpostare la password';

    public function handle(): int
    {
        $email = $this->argument('email');

        if (! User::where('email', $email)->exists()) {
            $this->error("Nessun utente con email {$email}");

            return self::FAILURE;
        }

        $status = Password::sendResetLink(['email' => $email]);

        if ($status !== Password::RESET_LINK_SENT) {
            $this->error('Invio fallito: '.$status);

            return self::FAILURE;
        }

        $this->info("Email inviata a {$email}.");

        if (config('mail.default') === 'log') {
            $this->line('Mailer=log → controlla storage/logs/laravel.log per il link.');
        }

        return self::SUCCESS;
    }
}
