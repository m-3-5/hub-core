<?php

namespace App\Notifications;

use App\Models\ClassifiedAd;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** A Max: un privato ha confermato un annuncio, va controllato prima di andare online. */
class ClassifiedToReviewNotification extends Notification
{
    public function __construct(private readonly ClassifiedAd $ad) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Annuncio da controllare: «'.$this->ad->title.'»')
            ->greeting('Nuovo annuncio di un privato')
            ->line('**'.$this->ad->title.'** · '.$this->ad->categoryLabel().' · '.$this->ad->zone.($this->ad->priceLabel() ? ' · '.$this->ad->priceLabel() : ''))
            ->line(mb_strimwidth($this->ad->description, 0, 400, '…'))
            ->line('Da: '.($this->ad->contact_name ?: $this->ad->tenant->name).($this->ad->source ? ' (arriva da '.$this->ad->source.')' : ''))
            ->action('Approva o rifiuta', route('admin.classified-review.index'))
            ->salutation('Hub Core');
    }
}
