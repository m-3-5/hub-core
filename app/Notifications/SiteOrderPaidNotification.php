<?php

namespace App\Notifications;

use App\Models\SiteOrder;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Al titolare: un cliente ha pagato l'euro di partenza dalla landing siti web. */
class SiteOrderPaidNotification extends Notification
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
            ->from(config('mail.from.address'), 'Hub Core — richieste sito')
            ->subject('Nuovo cliente sito web: '.$o->name.' ('.$o->planLabel().') ha pagato '.SiteOrder::euro($o->start_cents).' €')
            ->greeting('Nuovo ordine «'.SiteOrder::euro($o->start_cents).' € per partire»!')
            ->line('**Nome:** '.$o->name)
            ->line('**Telefono:** '.$o->phone)
            ->line('**Email:** '.$o->email)
            ->line('**Pacchetto:** '.$o->planLabel().' — '.SiteOrder::euro($o->package_cents).' € + IVA')
            ->line('**Dopo '.$o->trial_days.' giorni:** '.$o->installments.' rate da '.SiteOrder::euro($o->installment_cents).' € al mese (fine rate: '.$o->completes_at?->timezone(config('app.timezone'))->format('d/m/Y').')')
            ->action('Scrivi su WhatsApp', 'https://wa.me/'.$o->dialNumber())
            ->line('Lo trovi anche in Dashboard → Richieste sito.')
            ->replyTo($o->email, $o->name)
            ->salutation('Hub Core');
    }
}
