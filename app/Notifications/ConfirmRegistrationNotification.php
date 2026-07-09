<?php

namespace App\Notifications;

use App\Models\PendingRegistration;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ConfirmRegistrationNotification extends Notification
{
    // Nota: niente ShouldQueue qui — il worker della coda non risulta attivo su Plesk,
    // quindi un job in coda non partirebbe mai. Invio sincrono finché non sistemiamo la coda (Fase 4).
    public function __construct(private readonly PendingRegistration $pending) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('registration.confirm', ['token' => $this->pending->token]);

        return (new MailMessage)
            ->subject('Conferma la tua registrazione a Hub Core')
            ->greeting('Ciao, '.$this->pending->contact_name.'!')
            ->line('Hai richiesto di registrare **'.$this->pending->name.'** su Hub Core.')
            ->line('Clicca il pulsante qui sotto per confermare il tuo indirizzo email e completare la registrazione.')
            ->action('Conferma email', $url)
            ->line('Il link è valido per 48 ore.')
            ->line('Se non hai richiesto tu questa registrazione, puoi ignorare questa email.')
            ->salutation('A presto, il team Hub Core');
    }
}
