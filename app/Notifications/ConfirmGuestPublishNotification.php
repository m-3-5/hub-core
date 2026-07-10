<?php

namespace App\Notifications;

use App\Models\Promo;
use App\Models\Tenant;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ConfirmGuestPublishNotification extends Notification
{
    public function __construct(
        private readonly Tenant $tenant,
        private readonly Promo $promo,
        private readonly string $token,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url(route('guest.confirm-publish', ['token' => $this->token], false));

        return (new MailMessage)
            ->subject('Conferma per pubblicare «'.$this->promo->title.'»')
            ->greeting('Ci siamo quasi!')
            ->line('Hai creato **'.$this->promo->title.'** per **'.$this->tenant->name.'** su Hub Core.')
            ->line('Clicca qui sotto per confermare la tua email e pubblicarla davvero.')
            ->action('Conferma e pubblica', $url)
            ->line('Il link è valido per 48 ore. Se non hai richiesto tu questa promo, ignora pure questa email.')
            ->salutation('A presto, il team Hub Core');
    }
}
