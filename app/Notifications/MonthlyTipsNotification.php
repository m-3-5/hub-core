<?php

namespace App\Notifications;

use App\Models\Tenant;
use App\Support\MarketingEmails;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** «L'idea della settimana» per far crescere l'attività con Hub Core, con il link per non riceverle più. */
class MonthlyTipsNotification extends Notification
{
    /** @param  array<int, array{0: string, 1: string, 2: string, 3?: string}>  $tips  la prima è la principale */
    public function __construct(private readonly Tenant $tenant, private readonly array $tips) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $unsubscribe = MarketingEmails::unsubscribeUrl($this->tenant);
        [$title, $text, $url] = $this->tips[0];
        // Oggetto: il titolo senza emoji iniziale, con il nome dell'attività.
        $subject = trim(preg_replace('/^[^\p{L}\p{N}]+/u', '', $title) ?? $title).' — '.$this->tenant->name;

        $mail = (new MailMessage)
            ->subject($subject)
            ->greeting('Ciao, '.$notifiable->name.'!')
            ->line('**'.$title.'**')
            ->line($text)
            ->action('Provalo ora', $url);

        if ($extra = $this->tips[1] ?? null) {
            $mail->line('**Lo sapevi?** '.$extra[0].' — '.$extra[1].' [Scopri →]('.$extra[2].')');
        }

        if ($offer = config('hub.marketing.offer')) {
            $mail->line('🎁 **Offerta del momento:** '.$offer);
        }

        return $mail
            ->line('Ricevi un\'idea a settimana perché usi Hub Core. Non vuoi più queste email? [Non mandatemele più]('.$unsubscribe.') — le email su pagamenti e accesso continuano ad arrivare.')
            ->salutation('A presto, il team Hub Core')
            ->withSymfonyMessage(fn ($m) => $m->getHeaders()->addTextHeader('List-Unsubscribe', '<'.$unsubscribe.'>'));
    }
}
