<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestMail extends Command
{
    protected $signature = 'hub:test-mail {email : Destinatario del messaggio di prova}';

    protected $description = 'Invia un\'email di prova per verificare la configurazione SMTP/sendmail';

    public function handle(): int
    {
        $email = $this->argument('email');
        $mailer = config('mail.default');

        $this->line('Mailer attivo: '.$mailer);
        $this->line('From: '.config('mail.from.address').' ('.config('mail.from.name').')');

        if ($mailer === 'log') {
            $this->warn('MAIL_MAILER=log — l\'email finirà in storage/logs/laravel.log, non nella casella reale.');
        }

        try {
            Mail::raw(
                "Questa è un'email di prova da Hub Core (".config('app.url').").\n\nSe la ricevi, l'invio mail è configurato correttamente.",
                function ($message) use ($email) {
                    $message->to($email)->subject('Test email Hub Core — '.now()->format('d/m/Y H:i'));
                }
            );
        } catch (\Throwable $e) {
            $this->error('Invio fallito: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Email di prova inviata a {$email}.");

        if ($mailer === 'log') {
            $this->line('Controlla storage/logs/laravel.log per il contenuto.');
        }

        return self::SUCCESS;
    }
}
