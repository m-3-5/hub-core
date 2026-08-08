<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Notifications\TenantFeedbackRequestNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class SendFeedbackEmails extends Command
{
    protected $signature = 'hub:send-feedback-emails {--send : Invia davvero le email (senza, mostra solo l\'anteprima)} {--tenant= : Limita a un solo tenant (slug), utile per testare}';

    protected $description = 'Manda a ogni cliente una richiesta di feedback con domande in base a cosa ha creato — di default mostra solo un\'anteprima, non invia nulla';

    public function handle(): int
    {
        $campaign = 'feedback-'.now()->format('Y-m');

        $tenants = Tenant::query()
            ->when($this->option('tenant'), fn ($q, $slug) => $q->where('slug', $slug))
            ->whereDoesntHave('feedbackResponses', fn ($q) => $q->where('campaign', $campaign))
            ->with('users')
            ->get()
            ->filter(fn (Tenant $t) => $t->users->isNotEmpty());

        if ($tenants->isEmpty()) {
            $this->info('Nessun tenant da contattare (o hanno già risposto a questa campagna).');

            return self::SUCCESS;
        }

        $this->info('Campagna: '.$campaign);
        $this->table(
            ['Tenant', 'Email destinatari'],
            $tenants->map(fn (Tenant $t) => [$t->name, $t->users->pluck('email')->implode(', ')]),
        );

        if (! $this->option('send')) {
            $this->warn('Anteprima soltanto — nessuna email inviata. Rilancia con --send per inviare davvero.');

            return self::SUCCESS;
        }

        if (! $this->confirm('Confermi l\'invio reale a '.$tenants->count().' cliente/i?')) {
            $this->warn('Annullato.');

            return self::SUCCESS;
        }

        foreach ($tenants as $tenant) {
            Notification::send($tenant->users, new TenantFeedbackRequestNotification($tenant, $campaign));
            $this->line('Inviata a '.$tenant->name);
        }

        $this->info('Fatto — '.$tenants->count().' email inviate.');

        return self::SUCCESS;
    }
}
