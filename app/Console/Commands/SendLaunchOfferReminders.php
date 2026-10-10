<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Notifications\LaunchOfferReminderNotification;
use Illuminate\Console\Command;
use Throwable;

/** Una sola email per cliente, appena finita la prima settimana gratuita, per chiedere l'euro di lancio. */
class SendLaunchOfferReminders extends Command
{
    protected $signature = 'hub:launch-offer-reminders {--dry-run : Mostra a chi scriverebbe senza inviare}';

    protected $description = 'Avvisa i clienti la cui settimana gratuita è finita e propone l\'offerta di lancio';

    public function handle(): int
    {
        $sent = 0;

        Tenant::query()
            ->where('subscription_status', 'trialing')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', now())
            ->where('trial_ends_at', '>=', now()->subDays(30))
            ->each(function (Tenant $tenant) use (&$sent) {
                $settings = $tenant->settings ?? [];

                // Vecchi clienti in prova (prima di questa regola) non ricevono nulla.
                if (empty($settings['free_week']) || ! empty($settings['launch_reminder_sent_at'])) {
                    return;
                }

                $user = $tenant->users()->first();

                // Gli ospiti non ancora registrati hanno un'email finta: niente da inviare.
                if (! $user || str_ends_with((string) $user->email, '@guest.hub-core.local')) {
                    return;
                }

                $this->line($tenant->name.' <'.$user->email.'>');

                if ($this->option('dry-run')) {
                    return;
                }

                try {
                    $user->notify(new LaunchOfferReminderNotification($tenant));
                } catch (Throwable $e) {
                    report($e);

                    return;
                }

                $settings['launch_reminder_sent_at'] = now()->toIso8601String();
                $tenant->forceFill(['settings' => $settings])->save();
                $sent++;
            });

        $this->info($sent.' promemoria inviati.');

        return self::SUCCESS;
    }
}
