<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Crea un cliente (attività) già pronto, con il titolare collegato. La password non passa mai da qui:
 * il titolare riceve un'email per sceglierla da solo. Esempio: hub:create-tenant iflem "IFLEM S.n.c." pmelfi@iflem.it --owner-name="Pasquale Melfi"
 */
class CreateTenant extends Command
{
    protected $signature = 'hub:create-tenant
        {slug : Identificativo nell\'indirizzo (minuscolo, senza spazi), es. iflem}
        {name : Nome dell\'attività}
        {owner_email : Email del titolare (riceverà il link per scegliere la password)}
        {--owner-name= : Nome del titolare}
        {--type=azienda : azienda, privato o ente}
        {--website= : Sito web (https://...)}
        {--phone= : Telefono}
        {--color= : Colore principale, es. #b8860b}
        {--no-email : Non inviare l\'email di accesso}';

    protected $description = 'Crea un cliente con il titolare collegato e gli manda il link per scegliere la password';

    public function handle(): int
    {
        $slug = (string) $this->argument('slug');
        $email = strtolower(trim((string) $this->argument('owner_email')));
        $type = (string) $this->option('type');

        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            $this->error('L\'identificativo deve avere solo lettere minuscole, numeri e trattini.');

            return self::FAILURE;
        }

        if (! in_array($type, ['azienda', 'privato', 'ente'], true)) {
            $this->error('Il tipo deve essere azienda, privato o ente.');

            return self::FAILURE;
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('L\'email del titolare non è valida.');

            return self::FAILURE;
        }

        $color = $this->option('color');

        if ($color && ! preg_match('/^#[0-9a-fA-F]{6}$/', (string) $color)) {
            $this->error('Il colore deve essere nel formato #rrggbb.');

            return self::FAILURE;
        }

        $website = $this->option('website');

        if ($website && ! preg_match('#^https://#i', (string) $website)) {
            $this->error('Il sito deve iniziare con https://');

            return self::FAILURE;
        }

        if (Tenant::where('slug', $slug)->exists()) {
            $this->error("Esiste già un'attività con identificativo «{$slug}»: non tocco nulla.");

            return self::FAILURE;
        }

        $tenant = Tenant::create(array_filter([
            'slug' => $slug,
            'name' => (string) $this->argument('name'),
            'type' => $type,
            'plan' => 'demo',
            'website' => $website ?: null,
            'phone' => $this->option('phone') ?: null,
            'primary_color' => $color ?: null,
            // Cliente seguito da noi: nessuna prova a scadenza, può pubblicare subito.
            'subscription_status' => 'free',
            'guest_verified_at' => now(),
        ], fn ($v) => $v !== null));

        $user = User::where('email', $email)->first();
        $created = false;

        if (! $user) {
            $user = User::create([
                'name' => (string) ($this->option('owner-name') ?: Str::before($email, '@')),
                'email' => $email,
                'password' => Hash::make(Str::random(40)),
            ]);
            $created = true;
        }

        $tenant->users()->syncWithoutDetaching([$user->id => ['role' => 'admin']]);

        $this->info("Attività «{$tenant->name}» creata ({$slug}).");
        $this->info(($created ? 'Utente creato: ' : 'Utente già esistente: ').$email);

        if (! $this->option('no-email')) {
            $status = Password::sendResetLink(['email' => $email]);
            $this->line($status === Password::RESET_LINK_SENT
                ? "Email per scegliere la password inviata a {$email}."
                : 'Email non inviata ('.$status.'): usa php artisan hub:send-access-link '.$email);
        }

        $this->line('App: '.route('app.home', $tenant));
        $this->line('Promo pubbliche: '.route('promo.archive', $tenant));

        return self::SUCCESS;
    }
}
