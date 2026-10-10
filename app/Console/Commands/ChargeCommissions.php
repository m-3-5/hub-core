<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\TenantModuleCharge;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use M35\HubPayments\Support\OrderSummary;

class ChargeCommissions extends Command
{
    protected $signature = 'hub:charge-commissions {--period= : Mese YYYY-MM (default: il mese scorso)} {--dry-run : Mostra senza scrivere}';

    protected $description = 'Porta nel registro costi le commissioni maturate sulle vendite del canale hub nel mese (restano da incassare: mai addebitate in automatico sulla carta)';

    public function handle(): int
    {
        $period = $this->option('period') ?: now()->subMonthNoOverflow()->format('Y-m');

        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            $this->error('Periodo non valido: usa YYYY-MM.');

            return self::FAILURE;
        }

        $created = 0;

        foreach (Tenant::query()->get() as $tenant) {
            $orders = OrderSummary::paid($tenant->id, $period)
                ->where('channel', 'hub')
                // Sui pagamenti protetti la commissione si trattiene direttamente al momento del rilascio dei soldi.
                ->where('flow', 'direct')
                ->where('commission_cents', '>', 0)
                ->whereNull('commission_charge_id')
                ->get();

            if ($orders->isEmpty()) {
                continue;
            }

            $amount = (int) $orders->sum('commission_cents');
            $this->line("{$tenant->name}: {$orders->count()} ordini hub, commissione € ".number_format($amount / 100, 2, ',', '.'));

            if ($this->option('dry-run')) {
                continue;
            }

            DB::transaction(function () use ($tenant, $orders, $amount, $period, &$created) {
                $charge = TenantModuleCharge::create([
                    'tenant_id' => $tenant->id,
                    'module' => 'servizi',
                    'charge_type' => 'commission',
                    'period' => $period,
                    'description' => 'Commissione vendite su inm35.it '.$period.' ('.$orders->count().' ordini)',
                    'amount_cents' => $amount,
                    'paid' => false,
                ]);

                $orders->each(fn ($order) => $order->update(['commission_charge_id' => $charge->id]));
                $created++;
            });
        }

        $this->info($this->option('dry-run') ? 'Prova: nessuna voce scritta.' : "Voci create nel registro: {$created}.");

        return self::SUCCESS;
    }
}
