<?php

namespace M35\HubSitebuilder\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Conferma che il sito è stato rimosso. */
class SiteRemovedNotification extends Notification
{
    public function __construct(private readonly string $tenantName, private readonly string $reasonLabel) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->from(config('mail.from.address'), 'Hub Core')
            ->subject('Il sito di '.$this->tenantName.' è stato rimosso')
            ->greeting('Sito rimosso')
            ->line('Il sito di '.$this->tenantName.' non è più online. Motivo: '.$this->reasonLabel.'.')
            ->line('Se vuoi ricrearlo puoi farlo quando vuoi dalla tua area: ci vogliono pochi minuti.')
            ->salutation('Hub Core');
    }
}
