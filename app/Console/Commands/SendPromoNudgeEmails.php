<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Notifications\PromoNudgeNotification;
use App\Support\TenantPromoQuota;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class SendPromoNudgeEmails extends Command
{
    protected $signature = 'hub:send-promo-nudge-emails {--send : Invia davvero le email e assegna il bonus (senza, mostra solo l\'anteprima)} {--tenant= : Limita a un solo tenant (slug), utile per testare}';

    protected $description = 'Campagna mensile: promo extra omaggio + invito a lasciare feedback — di default mostra solo un\'anteprima, non invia nulla';

    public function handle(): int
    {
        $tenants = Tenant::query()
            ->when($this->option('tenant'), fn ($q, $slug) => $q->where('slug', $slug))
            ->with('users')
            ->get()
            ->filter(fn (Tenant $t) => $t->users->isNotEmpty());

        if ($tenants->isEmpty()) {
            $this->info('Nessun tenant da contattare.');

            return self::SUCCESS;
        }

        $this->info('Promo bonus da assegnare: +1 slot per tenant.');
        $this->table(
            ['Tenant', 'Email destinatari'],
            $tenants->map(fn (Tenant $t) => [$t->name, $t->users->pluck('email')->implode(', ')]),
        );

        if (! $this->option('send')) {
            $this->warn('Anteprima soltanto — nessuna email inviata, nessun bonus assegnato. Rilancia con --send per inviare davvero.');

            return self::SUCCESS;
        }

        if (! $this->confirm('Confermi l\'invio reale a '.$tenants->count().' cliente/i, con assegnazione del bonus?')) {
            $this->warn('Annullato.');

            return self::SUCCESS;
        }

        foreach ($tenants as $tenant) {
            TenantPromoQuota::grantBonusCredits($tenant, 1);
            Notification::send($tenant->users, new PromoNudgeNotification($tenant));
            $this->line('Inviata a '.$tenant->name.' (+1 promo bonus assegnata)');
        }

        $this->info('Fatto — '.$tenants->count().' email inviate.');

        return self::SUCCESS;
    }
}
