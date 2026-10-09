<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\WordPressWebhookDispatcher;
use App\Support\TenantSiteSync;
use Illuminate\Console\Command;

class SiteSync extends Command
{
    protected $signature = 'hub:site-sync {tenant : Slug dell\'azienda}
        {url? : Indirizzo che riceve gli avvisi del sito (https)}
        {--secret= : Segreto di firma dedicato (se manca vale HUB_WEBHOOK_SECRET)}
        {--promos : Le promo avvisano anche questo indirizzo}
        {--no-promos : Le promo non avvisano questo indirizzo}
        {--keep-legacy : Le promo avvisano anche il vecchio indirizzo globale (HUB_WEBHOOK_URL)}
        {--drop-legacy : Le promo non avvisano più il vecchio indirizzo globale (HUB_WEBHOOK_URL)}
        {--auto : Attiva anche l\'avviso automatico al sito (auto_site_sync)}
        {--clear : Rimuove l\'indirizzo del tenant}
        {--test : Invia un avviso di prova e mostra la risposta}';

    protected $description = 'Imposta o mostra l\'indirizzo del sito che l\'hub avvisa quando cambiano catalogo e promo';

    public function handle(WordPressWebhookDispatcher $dispatcher): int
    {
        $tenant = Tenant::where('slug', $this->argument('tenant'))->first();

        if (! $tenant) {
            $this->error('Azienda non trovata.');

            return self::FAILURE;
        }

        if ($this->option('clear')) {
            TenantSiteSync::clear($tenant);
            $this->info('Indirizzo del tenant rimosso: tornano validi gli indirizzi globali del .env.');
        } else {
            $changes = [];

            if ($url = $this->argument('url')) {
                if (! TenantSiteSync::isValidUrl($url)) {
                    $this->error('Indirizzo non valido: serve un URL https completo.');

                    return self::FAILURE;
                }

                $changes['url'] = $url;
            }

            if ($this->option('secret')) {
                $changes['secret'] = $this->option('secret');
            }

            if ($this->option('promos')) {
                $changes['promos'] = true;
            }

            if ($this->option('no-promos')) {
                $changes['promos'] = false;
            }

            if ($this->option('keep-legacy')) {
                $changes['legacy_promos'] = true;
            }

            if ($this->option('drop-legacy')) {
                $changes['legacy_promos'] = false;
            }

            if ($changes && ! TenantSiteSync::url($tenant) && ! isset($changes['url'])) {
                $this->error('Prima imposta l\'indirizzo: hub:site-sync '.$tenant->slug.' https://…');

                return self::FAILURE;
            }

            if ($changes) {
                TenantSiteSync::update($tenant, $changes);
                $this->info('Impostazioni salvate.');
            }

            if ($this->option('auto')) {
                $settings = $tenant->settings ?? [];
                $settings['auto_site_sync'] = true;
                $tenant->forceFill(['settings' => $settings])->save();
                $this->info('Avviso automatico al sito attivato.');
            }
        }

        $tenant->refresh();
        $this->report($tenant);

        if ($this->option('test')) {
            $result = $dispatcher->ping($tenant);

            if (! $result) {
                $this->warn('Nessun indirizzo del tenant: niente da provare.');

                return self::FAILURE;
            }

            if ($result['status'] === null) {
                $this->error('Prova fallita: '.$result['error']);

                return self::FAILURE;
            }

            $this->line('Prova inviata a '.$result['url'].' → risposta HTTP '.$result['status'].($result['status'] >= 200 && $result['status'] < 300 ? ' (ok)' : ' (il sito ha rifiutato l\'avviso)'));
        }

        return self::SUCCESS;
    }

    private function report(Tenant $tenant): void
    {
        $this->line('Azienda: '.$tenant->name.' ('.$tenant->slug.')');
        $this->line('Avviso automatico (auto_site_sync): '.($tenant->hasAutoSiteSync() ? 'attivo' : 'SPENTO — senza questo non parte nessun avviso (usa --auto)'));
        $this->line('Indirizzo del tenant: '.(TenantSiteSync::url($tenant) ?? '— (valgono gli indirizzi globali del .env)'));

        if (TenantSiteSync::url($tenant)) {
            $this->line('Segreto: '.(TenantSiteSync::secret($tenant) ? 'dedicato' : 'HUB_WEBHOOK_SECRET'));
            $this->line('Servizi e prodotti → indirizzo del tenant');
            $this->line('Promo → '.(TenantSiteSync::sendsPromos($tenant) ? 'indirizzo del tenant' : 'non avvisano il tenant')
                .(config('services.hub.webhook_url') && TenantSiteSync::sendsLegacyPromos($tenant) ? ' + vecchio indirizzo globale' : ''));
        } else {
            $this->line('Servizi e prodotti → '.(config('services.hub.services_webhook_url') ?: config('services.hub.webhook_url') ?: 'nessuno'));
            $this->line('Promo → '.(config('services.hub.webhook_url') ?: 'nessuno'));
        }
    }
}
