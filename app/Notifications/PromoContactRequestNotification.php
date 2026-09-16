<?php

namespace App\Notifications;

use App\Models\Promo;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PromoContactRequestNotification extends Notification
{
    public function __construct(
        private readonly Promo $promo,
        private readonly string $visitorName,
        private readonly ?string $visitorEmail,
        private readonly ?string $visitorPhone,
        private readonly string $message,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->from(config('mail.from.address'), 'Hub Core - '.$this->promo->tenant->name)
            ->subject('Nuovo contatto da «'.$this->promo->title.'»')
            ->greeting('Hai un nuovo messaggio!')
            ->line('**Da:** '.$this->visitorName);

        if ($this->visitorEmail) {
            $mail->line('**Email:** '.$this->visitorEmail);
        }

        if ($this->visitorPhone) {
            $mail->line('**Telefono:** '.$this->visitorPhone);
        }

        $mail->line('**Messaggio:**')
            ->line($this->message)
            ->line('Rispondi direttamente a questa email o contatta la persona ai recapiti sopra.')
            ->line('Puoi rivedere questo e tutti gli altri messaggi ricevuti, e segnarli come gestiti una volta risposto, nella sezione "Messaggi clienti" del tuo pannello.')
            ->action('Vai ai messaggi clienti', route('admin.customer-tickets.index', $this->promo->tenant))
            ->salutation('Hub Core');

        if ($this->visitorEmail) {
            $mail->replyTo($this->visitorEmail, $this->visitorName);
        }

        if ($monitor = config('mail.leads_monitor_email')) {
            $mail->bcc($monitor);
        }

        return $mail;
    }
}
