<?php

namespace App\Notifications;

use App\Models\SiteOrder;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Al cliente: conferma del pagamento e cosa succede nei giorni di prova. */
class SiteOrderWelcomeNotification extends Notification
{
    public function __construct(private readonly SiteOrder $order) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $o = $this->order;

        return (new MailMessage)
            ->from(config('mail.from.address'), 'M 3.5')
            ->subject('Abbiamo ricevuto il tuo pagamento — iniziamo il tuo sito '.$o->planLabel())
            ->greeting('Ciao '.$o->name.', grazie!')
            ->line('Abbiamo ricevuto '.SiteOrder::euro($o->start_cents).' € per far partire il tuo sito web **'.$o->planLabel().'**.')
            ->line('Ti contattiamo a breve per iniziare insieme: ti aiutiamo a creare il sito con la nostra intelligenza artificiale, partendo dalle foto e dai testi della tua attività.')
            ->line('**Come funziona il pagamento:** hai '.$o->trial_days.' giorni di prova. Dopo parte un abbonamento a riscatto di '.$o->installments.' rate mensili da '.SiteOrder::euro($o->installment_cents).' € + IVA; finite le rate il sito è tuo e l\'addebito si ferma da solo.')
            ->line(config('landing.start.terms'))
            ->line('Se vuoi scriverci subito: WhatsApp https://wa.me/'.preg_replace('/\D+/', '', (string) config('landing.whatsapp')))
            ->salutation('M 3.5 S.R.L. — '.config('landing.place').', '.config('landing.city'));
    }
}
