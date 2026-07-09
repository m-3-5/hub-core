<?php

namespace App\Console\Commands;

use App\Models\TenantModuleCharge;
use App\Services\HubBillingService;
use Illuminate\Console\Command;
use RuntimeException;

class ChargePendingModuleCharges extends Command
{
    protected $signature = 'hub:charge-pending-module-charges';

    protected $description = 'Addebita automaticamente sulla carta salvata le voci non pagate del registro costi (extra oltre quota), per i tenant con abbonamento hub attivo';

    public function handle(): int
    {
        $secretKey = config('services.hub_billing.secret_key');

        if (! $secretKey) {
            $this->warn('Fatturazione hub non configurata (HUB_STRIPE_SECRET_KEY mancante) — nessun addebito tentato.');

            return self::SUCCESS;
        }

        $service = new HubBillingService($secretKey);

        $charges = TenantModuleCharge::query()
            ->where('paid', false)
            ->whereHas('tenant', fn ($q) => $q->whereNotNull('stripe_customer_id'))
            ->with('tenant')
            ->get();

        if ($charges->isEmpty()) {
            $this->info('Nessuna voce da addebitare.');

            return self::SUCCESS;
        }

        $charged = 0;
        $failed = 0;

        foreach ($charges as $charge) {
            try {
                $intent = $service->chargeOffSession(
                    $charge->tenant,
                    $charge->amount_cents,
                    'Hub Core — '.$charge->description,
                );

                $charge->update([
                    'paid' => true,
                    'paid_at' => now(),
                    'description' => $charge->description.' [PaymentIntent: '.$intent['id'].']',
                ]);

                $charged++;
                $this->info("Addebitato: tenant {$charge->tenant->name}, €".number_format($charge->amount_cents / 100, 2).", {$charge->description}");
            } catch (RuntimeException $e) {
                $failed++;
                $this->error("Fallito: tenant {$charge->tenant->name} — {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("Riepilogo: {$charged} addebitate, {$failed} fallite.");

        return self::SUCCESS;
    }
}
