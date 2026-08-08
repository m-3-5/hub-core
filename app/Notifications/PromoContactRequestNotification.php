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
            ->salutation('Hub Core');

        if ($this->visitorEmail) {
            $mail->replyTo($this->visitorEmail, $this->visitorName);
        }

        return $mail;
    }
}
