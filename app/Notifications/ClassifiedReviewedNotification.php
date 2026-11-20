<?php

namespace App\Notifications;

use App\Models\ClassifiedAd;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Al privato: il suo annuncio è online (per 30 giorni) oppure non è stato approvato. */
class ClassifiedReviewedNotification extends Notification
{
    public function __construct(private readonly ClassifiedAd $ad, private readonly bool $approved, private readonly ?string $reason = null) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        if ($this->approved) {
            return (new MailMessage)
                ->subject('Il tuo annuncio è online: «'.$this->ad->title.'»')
                ->greeting('Fatto!')
                ->line('Il tuo annuncio è online fino al '.$this->ad->expires_at?->timezone(config('app.timezone'))->format('d/m/Y').'.')
                ->line('Chi è interessato ti scrive dal modulo dell\'annuncio: ti arriva qui per email.')
                ->action('Vedi l\'annuncio', $this->ad->publicUrl())
                ->salutation('A presto, Hub Core · inm35.it');
        }

        return (new MailMessage)
            ->subject('Annuncio non approvato: «'.$this->ad->title.'»')
            ->greeting('Ciao!')
            ->line('Il tuo annuncio non è stato pubblicato.'.($this->reason ? ' Motivo: '.$this->reason : ''))
            ->line('Puoi scriverne uno nuovo quando vuoi.')
            ->action('Nuovo annuncio', route('classifieds.private.create'))
            ->salutation('A presto, Hub Core · inm35.it');
    }
}
