<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Al passaggio dalle chiavi Stripe di prova a quelle reali, i clienti e gli abbonamenti creati in sandbox non esistono più in live:
 * lasciarli darebbe «No such customer». Questo comando li elenca (di default non modifica nulla) e, con --apply, li azzera.
 * Chi risultava attivo/scaduto/annullato per un pagamento di prova torna «in prova scaduta»: gli viene chiesto di attivare davvero.
 */
class ResetBillingTestData extends Command
{
    protected $signature = 'hub:reset-billing {slug? : Attività da azzerare} {--all : Tutte le attività con dati Stripe} {--apply : Applica davvero (senza, mostra soltanto)}';

    protected $description = 'Elenca/azzera i clienti e gli abbonamenti Stripe di prova (da fare al passaggio a Stripe live)';

    public function handle(): int
    {
        if (! $this->option('all') && ! $this->argument('slug')) {
            $this->error('Indica uno slug oppure --all.');

            return self::FAILURE;
        }

        $query = Tenant::query()->where(fn ($q) => $q->whereNotNull('stripe_customer_id')->orWhereNotNull('stripe_subscription_id'));

        if (! $this->option('all')) {
            $query->where('slug', $this->argument('slug'));
        }

        $tenants = $query->get();
        $apply = (bool) $this->option('apply');

        if ($tenants->isEmpty()) {
            $this->info('Nessuna attività con dati Stripe di prova.');

            return self::SUCCESS;
        }

        foreach ($tenants as $tenant) {
            $this->line(sprintf(
                '%s (%s) · stato %s · cliente %s · abbonamento %s',
                $tenant->slug,
                $tenant->type ?: 'azienda',
                $tenant->subscription_status,
                $tenant->stripe_customer_id ?: '—',
                $tenant->stripe_subscription_id ?: '—',
            ));

            if (! $apply) {
                continue;
            }

            $update = ['stripe_customer_id' => null, 'stripe_subscription_id' => null, 'billing_interval' => null];

            if (in_array($tenant->subscription_status, ['active', 'past_due', 'canceled'], true)) {
                $update['subscription_status'] = 'trialing';
                $update['trial_ends_at'] = now()->subMinute();
            }

            $before = ['customer' => $tenant->stripe_customer_id, 'subscription' => $tenant->stripe_subscription_id, 'status' => $tenant->subscription_status];
            $tenant->forceFill($update)->save();
            ActivityLog::record($tenant, 'billing_reset', input: $before);
        }

        $this->info($apply
            ? "Azzerate {$tenants->count()} attività."
            : "{$tenants->count()} attività con dati Stripe di prova. Per azzerarle: hub:reset-billing --all --apply");

        return self::SUCCESS;
    }
}
