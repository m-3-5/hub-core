<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewTicketNotification extends Notification
{
    public function __construct(
        private readonly Ticket $ticket,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tenant = $this->ticket->tenant;
        $url = url(route('admin.tickets.index'));

        return (new MailMessage)
            ->subject('Nuovo ticket da '.$tenant->name.' — rispondi entro 24 ore')
            ->greeting('Nuovo messaggio per Max')
            ->line('**Attività:** '.$tenant->name)
            ->line('**Messaggio:** '.$this->ticket->message)
            ->action('Rispondi al ticket', $url)
            ->salutation('Hub Core');
    }
}
