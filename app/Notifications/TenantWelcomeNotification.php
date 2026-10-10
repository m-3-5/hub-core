<?php

namespace App\Notifications;

use App\Models\Tenant;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TenantWelcomeNotification extends Notification
{
    public function __construct(
        private readonly Tenant $tenant,
        private readonly string $token,
        private readonly bool $viaLaunchOffer = false,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url(route('admin.password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        $included = config('hub-payments.services_included_quota', 3);
        $monthlyPrice = config('services.hub_billing.monthly_price_eur', 29);
        $annualPrice = config('services.hub_billing.annual_price_eur', 290);
        $trialDays = config('services.hub_billing.trial_days', 30);
        $trialEnds = $this->tenant->trial_ends_at?->format('d/m/Y');

        $mail = (new MailMessage)->greeting('Ciao, '.$notifiable->name.'!');

        if ($this->viaLaunchOffer) {
            $mail->subject('Attivazione ricevuta — benvenuto su Hub Core')
                ->line('Grazie per aver attivato **'.$this->tenant->name.'** su Hub Core con l\'offerta di lancio.')
                ->line('Il tuo abbonamento è già attivo con '.$included.' servizi a pagamento inclusi (link Stripe per i tuoi trattamenti/prodotti, pubblicabili sul tuo sito in automatico), e l\'attivazione del tuo primo modulo è coperta dall\'offerta.')
                ->line('Il primo addebito pieno (**€'.$monthlyPrice.'/mese**) partirà tra **'.$trialDays.' giorni** — fino ad allora usi tutto normalmente.');
        } else {
            $launch = config('services.hub_billing.launch_offer_price_eur', 1);
            $freeDays = config('services.hub_billing.free_days', 7);

            $mail->subject('Benvenuto su Hub Core — la tua prima settimana è gratis')
                ->line('Grazie per aver registrato **'.$this->tenant->name.'** su Hub Core.')
                ->line('Hai subito a disposizione **'.$freeDays.' giorni gratis**'.($trialEnds ? ' (fino al **'.$trialEnds.'**)' : '').', con '.$included.' servizi a pagamento inclusi (link Stripe per i tuoi trattamenti/prodotti, pubblicabili sul tuo sito in automatico). Non paghi nulla ora e non serve la carta.')
                ->line('Dopo la prima settimana, per continuare, ti chiediamo solo l\'offerta di lancio da **€'.$launch.'** (copre anche l\'attivazione del primo modulo). Poi altri '.config('services.hub_billing.trial_days', 30).' giorni di prova e solo dopo il canone: **€'.$monthlyPrice.'/mese** oppure **€'.$annualPrice.'/anno**. Disdici quando vuoi.');
        }

        return $mail
            ->action('Imposta password e inizia', $url)
            ->line('Il link è valido per **'.config('auth.passwords.users.expire').' minuti**.')
            ->line('Domande? Rispondi pure a questa email.')
            ->salutation('A presto, il team Hub Core');
    }
}
