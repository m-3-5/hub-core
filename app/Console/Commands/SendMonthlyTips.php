<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Notifications\MonthlyTipsNotification;
use App\Support\MarketingEmails;
use Illuminate\Console\Command;

/** Una volta al mese: idee su misura per ogni cliente (solo chi non si è disiscritto, mai due volte nello stesso mese). */
class SendMonthlyTips extends Command
{
    protected $signature = 'hub:monthly-tips {--dry : Mostra a chi andrebbe, senza inviare} {--tenant= : Solo questa attività (slug), come prova: non conta per il giro del mese}';

    protected $description = 'Manda le email «idee del mese» ai clienti (con link per disiscriversi)';

    public function handle(): int
    {
        $sent = 0;

        $only = $this->option('tenant');
        $query = Tenant::query()->orderBy('id');
        if ($only) {
            $query->where('slug', $only);
        }

        foreach ($query->cursor() as $tenant) {
            $last = $tenant->settings['marketing']['last_tips_at'] ?? null;

            if (MarketingEmails::optedOut($tenant) || (! $only && $last && now()->diffInDays($last, true) < 25)) {
                continue;
            }

            $to = MarketingEmails::recipients($tenant);
            $tips = MarketingEmails::tips($tenant);

            if ($to->isEmpty() || ! $tips) {
                continue;
            }

            $this->line($tenant->name.' → '.$to->pluck('email')->implode(', ').' ('.count($tips).' idee)');

            if ($this->option('dry')) {
                continue;
            }

            foreach ($to as $user) {
                $user->notify(new MonthlyTipsNotification($tenant, $tips));
            }

            if ($only) {
                $sent++;

                continue; // prova: il giro del mese resta com'è
            }

            $settings = $tenant->settings ?? [];
            $settings['marketing']['last_tips_at'] = now()->toIso8601String();
            $tenant->forceFill(['settings' => $settings])->save();
            $sent++;
        }

        $this->info(($this->option('dry') ? 'Prova: nessuna email inviata.' : "Inviate a {$sent} attività."));

        return self::SUCCESS;
    }
}
