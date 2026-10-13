<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use M35\HubPayments\Support\ProtectedPayout;
use RuntimeException;

class ReleaseProtectedPayments extends Command
{
    protected $signature = 'hub:release-protected-payments {--dry-run : Mostra cosa verrebbe rilasciato, senza muovere soldi}';

    protected $description = 'Gira ai venditori i pagamenti protetti la cui attesa è finita (conferma del compratore o giorni trascorsi senza segnalazioni)';

    public function handle(): int
    {
        $due = ProtectedPayout::due();

        if ($due->isEmpty()) {
            $this->info('Nessun pagamento protetto da rilasciare.');

            return self::SUCCESS;
        }

        $released = 0;
        $failed = 0;

        foreach ($due as $order) {
            $label = '#'.$order->id.' '.($order->tenant?->name ?? '').' '.number_format($order->amount_cents / 100, 2, ',', '.').' €';

            if ($this->option('dry-run')) {
                $this->line('Da rilasciare: '.$label.' (scadenza '.$order->release_at?->format('d/m/Y H:i').')');

                continue;
            }

            try {
                if (ProtectedPayout::release($order)) {
                    $released++;
                    $this->line('Rilasciato: '.$label);
                } else {
                    $this->line('Non rilasciato (bloccato o già fatto): '.$label);
                }
            } catch (RuntimeException $e) {
                // Un errore di Stripe su un ordine non ferma gli altri: si riprova alla prossima esecuzione.
                $failed++;
                report($e);
                $this->warn('Rimandato: '.$label.' — '.$e->getMessage());
            }
        }

        $this->info($this->option('dry-run') ? 'Prova: nessun trasferimento eseguito.' : "Rilasciati: {$released}. Rimandati per errore: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
