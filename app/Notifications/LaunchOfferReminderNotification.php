<?php

namespace App\Notifications;

use App\Models\Tenant;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Finita la prima settimana gratuita: si chiede l'euro dell'offerta di lancio per continuare. */
class LaunchOfferReminderNotification extends Notification
{
    public function __construct(private readonly Tenant $tenant) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $launch = config('services.hub_billing.launch_offer_price_eur', 1);

        return (new MailMessage)
            ->subject('La tua settimana gratuita su Hub Core è finita — continua con '.$launch.' €')
            ->greeting('Ciao '.$notifiable->name.'!')
            ->line('Sono passati i primi giorni gratuiti di **'.$this->tenant->name.'** su Hub Core: speriamo ti siano serviti.')
            ->line('Per continuare ti chiediamo solo l\'**offerta di lancio da '.$launch.' €** (copre anche l\'attivazione del primo modulo). Poi hai altri '.config('services.hub_billing.trial_days', 30).' giorni di prova e solo dopo parte il canone di **€'.config('services.hub_billing.monthly_price_eur', 29).'/mese**. Disdici quando vuoi.')
            ->action('Attiva con '.$launch.' €', route('admin.billing.show', $this->tenant))
            ->line('Se non vuoi continuare non devi fare nulla: non ti addebitiamo niente.')
            ->salutation('A presto, il team Hub Core');
    }
}
