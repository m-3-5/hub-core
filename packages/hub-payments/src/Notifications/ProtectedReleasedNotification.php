<?php

namespace M35\HubPayments\Notifications;

use App\Models\Tenant;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use M35\HubPayments\Models\PayableOrder;

/** Al venditore: i soldi di un pagamento protetto sono stati girati, con il dettaglio di commissioni e importo ricevuto. */
class ProtectedReleasedNotification extends Notification
{
    public function __construct(
        private readonly Tenant $tenant,
        private readonly PayableOrder $order,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $o = $this->order;
        $eur = fn (?int $cents) => number_format(((int) $cents) / 100, 2, ',', '.').' €';
        $items = collect($o->items)->map(fn ($i) => ($i['title'] ?? 'Articolo'))->implode(', ');

        $mail = (new MailMessage)
            ->from(config('mail.from.address'), 'Hub Core - '.$this->tenant->name)
            ->subject('Soldi in arrivo: '.$eur($o->payout_cents).' per l\'ordine #'.$o->id)
            ->greeting('Pagamento protetto liberato!')
            ->line('Ordine #'.$o->id.': '.$items)
            ->line('**Pagato dal cliente:** '.$eur($o->amount_cents))
            ->line('**Commissione Stripe:** − '.$eur($o->stripe_fee_cents));

        if ((int) $o->commission_cents > 0) {
            $mail->line('**Commissione Hub Core:** − '.$eur($o->commission_cents));
        }

        return $mail
            ->line('**Ti viene accreditato: '.$eur($o->payout_cents).'**')
            ->line($o->confirmed_at
                ? 'Il cliente ha confermato di aver ricevuto quanto acquistato.'
                : 'Sono passati i giorni di attesa senza segnalazioni del cliente.')
            ->line('I soldi arrivano sul tuo conto Stripe e poi, con i bonifici di Stripe, sul tuo IBAN (di solito entro pochi giorni lavorativi).')
            ->salutation('Hub Core');
    }
}
