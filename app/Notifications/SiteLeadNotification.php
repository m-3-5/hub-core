<?php

namespace App\Notifications;

use App\Models\SiteLead;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SiteLeadNotification extends Notification
{
    public function __construct(private readonly SiteLead $lead) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $lead = $this->lead;

        $mail = (new MailMessage)
            ->from(config('mail.from.address'), 'Hub Core — richieste sito')
            ->subject('Nuova richiesta sito web: '.$lead->name.' ('.$lead->packageLabel().')')
            ->greeting('Hai una nuova richiesta di preventivo!')
            ->line('**Nome:** '.$lead->name)
            ->line('**Telefono:** '.$lead->phone)
            ->line('**Email:** '.($lead->email ?: 'non indicata'))
            ->line('**Interessato a:** '.$lead->packageLabel());

        if ($lead->message) {
            $mail->line('**Messaggio:**')->line($lead->message);
        }

        $mail->action('Scrivi su WhatsApp', 'https://wa.me/'.$lead->dialNumber())
            ->line('Puoi rivedere tutte le richieste e segnarle come gestite da Dashboard → Richieste sito.')
            ->salutation('Hub Core');

        if ($lead->email) {
            $mail->replyTo($lead->email, $lead->name);
        }

        return $mail;
    }
}
