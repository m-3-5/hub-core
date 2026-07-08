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
        $price = config('hub-payments.services_paid_price', 9);

        return (new MailMessage)
            ->subject('Benvenuto su Hub Core — la tua demo gratuita è pronta')
            ->greeting('Ciao, '.$notifiable->name.'!')
            ->line('Grazie per aver registrato **'.$this->tenant->name.'** su Hub Core.')
            ->line('Hai subito a disposizione una **demo gratuita** con '.$included.' servizi a pagamento inclusi (link Stripe per i tuoi trattamenti/prodotti, pubblicabili sul tuo sito in automatico).')
            ->line('Dopo la demo, per continuare a usufruire dei servizi scontati basta un canone di **€'.$price.'/mese** — nessun impegno, nessuna carta richiesta ora.')
            ->action('Imposta password e inizia', $url)
            ->line('Il link è valido per **'.config('auth.passwords.users.expire').' minuti**.')
            ->line('Domande? Rispondi pure a questa email.')
            ->salutation('A presto, il team Hub Core');
    }
}
