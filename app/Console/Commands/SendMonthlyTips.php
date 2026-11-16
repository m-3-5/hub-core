<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Notifications\MonthlyTipsNotification;
use App\Support\MarketingEmails;
use Illuminate\Console\Command;

/** Un'idea a settimana per ogni cliente, a ciclo su tutto quello che offre inm35.it (solo chi non si è disiscritto). */
class SendMonthlyTips extends Command
{
    protected $signature = 'hub:weekly-tips {--dry : Mostra a chi andrebbe, senza inviare} {--tenant= : Solo questa attività (slug), come prova: non conta per il giro}';

    /** Il vecchio nome continua a funzionare. */
    protected $aliases = ['hub:monthly-tips'];

    protected $description = 'Manda l\'email «idea della settimana» ai clienti (con link per disiscriversi)';

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

            // Al massimo una a settimana (6 giorni di margine per il giro del martedì).
            if (MarketingEmails::optedOut($tenant) || (! $only && $last && now()->diffInDays($last, true) < 6)) {
                continue;
            }

            $to = MarketingEmails::recipients($tenant);
            $tips = MarketingEmails::tips($tenant);

            if ($to->isEmpty() || ! $tips) {
                continue;
            }

            $this->line($tenant->name.' → '.$to->pluck('email')->implode(', ').' ('.$tips[0][0].')');

            if ($this->option('dry')) {
                continue;
            }

            foreach ($to as $user) {
                $user->notify(new MonthlyTipsNotification($tenant, $tips));
            }

            if (! $only) {
                MarketingEmails::remember($tenant, $tips); // prova: il giro resta com'è
            }
            $sent++;
        }

        $this->info(($this->option('dry') ? 'Prova: nessuna email inviata.' : "Inviate a {$sent} attività."));

        return self::SUCCESS;
    }
}
