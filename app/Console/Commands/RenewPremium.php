<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Support\PayPerUse;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Pacchetti Base/Premium/Business (vedi App\Support\Packages): ogni giorno rinnova chi è in scadenza e vuole continuare,
 * scalando il mese dal credito. Se il credito non basta Premium finisce da solo: poi prezzi di favore
 * (premium.exit_cents per premium.exit_days giorni) e infine quelli normali. Promemoria: docs/FUNZIONI-INM35.md
 */
class RenewPremium extends Command
{
    protected $signature = 'hub:renew-premium';

    protected $description = 'Rinnova Premium dal credito per chi scade entro un giorno e non ha detto di fermarlo';

    public function handle(): int
    {
        if (! PayPerUse::premiumEnabled()) {
            return self::SUCCESS;
        }

        $renewed = $ended = 0;

        Tenant::query()->whereNotNull('settings')->get()->each(function (Tenant $tenant) use (&$renewed, &$ended) {
            $p = PayPerUse::premium($tenant);
            if (empty($p['until']) || empty($p['renew']) || ! empty($p['ended_at'])) {
                return;
            }
            if (Carbon::parse($p['until'])->gt(now()->addDay())) {
                return; // non ancora in scadenza
            }

            if (\App\Support\Packages::renew($tenant)) {
                $renewed++;

                return;
            }

            // credito finito: non si rinnova più (resta attivo fino a «until», poi prezzi di favore)
            $settings = $tenant->settings;
            $settings['ppu_premium']['renew'] = false;
            $settings['ppu_premium']['ended_at'] = now()->toIso8601String();
            $tenant->forceFill(['settings' => $settings])->save();
            $ended++;
        });

        $this->info("Pacchetti rinnovati: {$renewed}, non rinnovati per credito: {$ended}");

        return self::SUCCESS;
    }
}
