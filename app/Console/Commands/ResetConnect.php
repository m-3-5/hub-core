<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\Tenant;
use Illuminate\Console\Command;
use M35\HubPayments\Models\PayableOrder;
use M35\HubPayments\Support\TenantConnect;

/**
 * Azzera il collegamento Stripe Connect di una o più attività. Serve al passaggio dalle chiavi di prova (sandbox) a quelle
 * reali: gli account «acct_…» creati in sandbox non esistono in live e il venditore deve ricollegarsi con i dati veri.
 * Le condizioni economiche già accettate restano valide (non vengono toccate).
 */
class ResetConnect extends Command
{
    protected $signature = 'hub:reset-connect {slug? : Attività da azzerare} {--all : Tutte le attività collegate} {--dry-run : Mostra cosa farebbe senza modificare} {--force : Azzera anche se ci sono pagamenti ancora trattenuti o bloccati}';

    protected $description = 'Azzera il collegamento Stripe Connect (utile al passaggio da sandbox a live): il venditore deve ricollegarsi';

    public function handle(): int
    {
        if (! $this->option('all') && ! $this->argument('slug')) {
            $this->error('Indica uno slug oppure --all.');

            return self::FAILURE;
        }

        $tenants = $this->option('all')
            ? Tenant::all()
            : Tenant::where('slug', $this->argument('slug'))->get();

        if ($tenants->isEmpty()) {
            $this->error('Attività non trovata.');

            return self::FAILURE;
        }

        $reset = 0;
        $blocked = 0;

        foreach ($tenants as $tenant) {
            $accountId = TenantConnect::accountId($tenant);
            if ($accountId === null) {
                continue;
            }

            $open = PayableOrder::where('tenant_id', $tenant->id)
                ->where('flow', 'protected')
                ->whereIn('payout_status', ['held', 'frozen'])
                ->count();

            if ($open > 0 && ! $this->option('force')) {
                $this->warn("{$tenant->slug}: {$open} pagamenti protetti ancora trattenuti o bloccati — non azzerato (risolvili o usa --force).");
                $blocked++;

                continue;
            }

            $this->line(($this->option('dry-run') ? '[prova] ' : '')."{$tenant->slug}: scollego {$accountId}");

            if (! $this->option('dry-run')) {
                $settings = $tenant->settings ?? [];
                unset($settings['connect']);
                $tenant->forceFill(['settings' => $settings])->save();
                ActivityLog::record($tenant, 'connect_reset', input: ['account_id' => $accountId]);
            }

            $reset++;
        }

        $this->info("Collegamenti azzerati: {$reset}".($blocked ? " · non toccati: {$blocked}" : '').'.');

        return $blocked > 0 ? self::FAILURE : self::SUCCESS;
    }
}
