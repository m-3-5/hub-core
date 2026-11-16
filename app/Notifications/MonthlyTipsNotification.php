<?php

namespace App\Notifications;

use App\Models\Tenant;
use App\Support\MarketingEmails;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** «Consigli del mese» per far crescere l'attività con Hub Core, con il link per non riceverli più. */
class MonthlyTipsNotification extends Notification
{
    /** @param  array<int, array{0: string, 1: string, 2: string}>  $tips */
    public function __construct(private readonly Tenant $tenant, private readonly array $tips) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $unsubscribe = MarketingEmails::unsubscribeUrl($this->tenant);

        $mail = (new MailMessage)
            ->subject('Idee per '.$this->tenant->name.' questo mese')
            ->greeting('Ciao, '.$notifiable->name.'!')
            ->line('Ecco qualche idea per farti trovare da più clienti con Hub Core:');

        foreach ($this->tips as [$title, $text, $url]) {
            $mail->line('**'.$title.'** — '.$text.' [Vai →]('.$url.')');
        }

        if ($offer = config('hub.marketing.offer')) {
            $mail->line('🎁 **Offerta del momento:** '.$offer);
        }

        return $mail
            ->action('Apri Hub Core', route('app.home', $this->tenant))
            ->line('Ricevi questa email perché usi Hub Core. Non vuoi più queste idee? [Non mandatemele più]('.$unsubscribe.') — le email su pagamenti e accesso continuano ad arrivare.')
            ->salutation('A presto, il team Hub Core')
            ->withSymfonyMessage(fn ($m) => $m->getHeaders()->addTextHeader('List-Unsubscribe', '<'.$unsubscribe.'>'));
    }
}
