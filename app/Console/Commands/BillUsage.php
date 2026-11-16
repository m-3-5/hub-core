<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\TenantUsageEntry;
use App\Services\HubBillingService;
use App\Support\PayPerUse;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Paghi quello che pubblichi: addebita sulla carta salvata le pubblicazioni di almeno un mese fa,
 * tutte insieme (un solo prelievo per attività = una sola commissione Stripe).
 */
class BillUsage extends Command
{
    protected $signature = 'hub:bill-usage {--dry-run : mostra cosa addebiterebbe senza toccare niente}';

    protected $description = 'Addebita dopo un mese, in un colpo solo, le promo/prodotti/servizi pubblicati col sistema «paghi quello che pubblichi»';

    public function handle(): int
    {
        $secretKey = config('services.hub_billing.secret_key');

        $due = TenantUsageEntry::query()
            ->where('status', 'pending')
            ->where('bill_after', '<=', now())
            ->get()
            ->groupBy('tenant_id');

        if ($due->isEmpty()) {
            $this->info('Niente da addebitare.');

            return self::SUCCESS;
        }

        if (! $secretKey && ! $this->option('dry-run')) {
            $this->warn('HUB_STRIPE_SECRET_KEY mancante: nessun addebito.');

            return self::SUCCESS;
        }

        foreach ($due as $tenantId => $entries) {
            $tenant = Tenant::find($tenantId);
            $total = (int) $entries->sum('amount_cents');

            if (! $tenant || $total <= 0) {
                continue;
            }

            $label = 'Hub Core — '.$entries->count().' pubblicazion'.($entries->count() === 1 ? 'e' : 'i');

            if ($this->option('dry-run')) {
                $this->line("{$tenant->name}: ".PayPerUse::euro($total)." ({$entries->count()})");

                continue;
            }

            try {
                $intent = (new HubBillingService($secretKey))->chargeOffSession($tenant, $total, $label);
                $ok = ($intent['status'] ?? null) === 'succeeded';
            } catch (RuntimeException $e) {
                $ok = false;
                $intent = ['id' => null];
                $this->error("{$tenant->name}: {$e->getMessage()}");
            }

            TenantUsageEntry::whereKey($entries->pluck('id'))->update([
                'status' => $ok ? 'billed' : 'failed',
                'billed_at' => $ok ? now() : null,
                'payment_intent_id' => $intent['id'] ?? null,
            ]);

            if (! $ok) {
                // niente nuove pubblicazioni a pagamento finché non aggiunge un'altra carta
                PayPerUse::markCardFailed($tenant);
            }

            $this->info(($ok ? 'Addebitato' : 'Fallito').": {$tenant->name}, ".PayPerUse::euro($total));
        }

        return self::SUCCESS;
    }
}
