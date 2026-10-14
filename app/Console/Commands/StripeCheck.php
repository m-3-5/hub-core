<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Controllo del passaggio a Stripe live, senza mai mostrare i segreti: modalità della chiave, quanti segreti webhook
 * sono configurati (e di che tipo) e se Stripe accetta la chiave (nome dell'account, incassi e bonifici abilitati).
 */
class StripeCheck extends Command
{
    protected $signature = 'hub:stripe-check';

    protected $description = 'Verifica la chiave Stripe e i segreti webhook configurati (senza mostrarli)';

    public function handle(): int
    {
        $key = (string) config('services.hub_billing.secret_key');
        $mode = str_starts_with($key, 'sk_live_') || str_starts_with($key, 'rk_live_') ? 'LIVE (soldi veri)'
            : (str_starts_with($key, 'sk_test_') || str_starts_with($key, 'rk_test_') ? 'PROVA (sandbox)' : 'NON IMPOSTATA o non valida');

        $this->line('Chiave Stripe: '.$mode.($key !== '' ? ' · '.strlen($key).' caratteri · finisce con …'.substr($key, -4) : ''));

        $secrets = array_values(array_filter(array_map('trim', explode(',', (string) config('services.hub_billing.webhook_secret')))));
        $this->line('Segreti webhook: '.count($secrets).' configurati'.($secrets ? ' · finiscono con '.implode(', ', array_map(fn ($s) => '…'.substr($s, -4), $secrets)) : ''));

        if ($key === '') {
            return self::FAILURE;
        }

        $response = Http::withToken($key)->acceptJson()->get(rtrim((string) config('services.hub_billing.api_base', 'https://api.stripe.com'), '/').'/v1/account');

        if (! $response->successful()) {
            $this->error('Stripe NON accetta la chiave: '.($response->json('error.message') ?? 'errore '.$response->status()));

            return self::FAILURE;
        }

        $account = $response->json();
        $this->info('Stripe accetta la chiave · account '.($account['id'] ?? '?').' · '.($account['settings']['dashboard']['display_name'] ?? $account['business_profile']['name'] ?? 'senza nome'));
        $this->line('Incassi abilitati: '.(! empty($account['charges_enabled']) ? 'sì' : 'NO').' · bonifici abilitati: '.(! empty($account['payouts_enabled']) ? 'sì' : 'NO'));

        return self::SUCCESS;
    }
}
