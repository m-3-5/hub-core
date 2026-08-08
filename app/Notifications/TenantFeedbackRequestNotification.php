<?php

namespace App\Notifications;

use App\Models\Tenant;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class TenantFeedbackRequestNotification extends Notification
{
    public function __construct(
        private readonly Tenant $tenant,
        private readonly string $campaign = 'general',
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = URL::temporarySignedRoute(
            'feedback.show',
            now()->addDays(30),
            ['tenant' => $this->tenant->slug, 'c' => $this->campaign],
        );

        return (new MailMessage)
            ->subject('Aiutaci a migliorare Hub Core — 2 minuti, promesso')
            ->greeting('Ciao '.$this->tenant->name.'!')
            ->line('Stiamo migliorando Hub Core e la tua opinione conta davvero: qualche domanda veloce su quello che hai già creato, così sappiamo cosa sistemare e cosa aggiungere.')
            ->action('Rispondi in 2 minuti', $url)
            ->line('Il link resta valido per 30 giorni.')
            ->salutation('Grazie, il team Hub Core');
    }
}
