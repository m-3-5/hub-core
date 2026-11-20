<?php

namespace App\Notifications;

use App\Support\EmailLogin;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Link per entrare senza password (vale un'ora). Invio sincrono: su Plesk la coda non gira. */
class EmailLoginLinkNotification extends Notification
{
    public function __construct(private readonly string $url, private readonly string $what) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Il tuo link per '.$this->what)
            ->greeting('Ciao!')
            ->line('Tocca il pulsante per '.$this->what.'. Niente password.')
            ->action('Entra', $this->url)
            ->line('Il link vale '.EmailLogin::MINUTES.' minuti. Se non sei stato tu, ignora questa email.')
            ->salutation('A presto, Hub Core · inm35.it');
    }
}
