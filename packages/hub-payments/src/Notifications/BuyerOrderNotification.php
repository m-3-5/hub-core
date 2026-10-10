<?php

namespace M35\HubPayments\Notifications;

use App\Models\Tenant;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use M35\HubPayments\Models\PayableOrder;

/** Ricevuta per chi ha pagato con i pagamenti protetti: cosa ha comprato e come è protetto fino alla consegna. */
class BuyerOrderNotification extends Notification
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
        $order = $this->order;
        $total = number_format($order->amount_cents / 100, 2, ',', '.').' €';

        $mail = (new MailMessage)
            ->from(config('mail.from.address'), 'Hub Core - '.$this->tenant->name)
            ->subject('Pagamento ricevuto — '.$this->tenant->name.' ('.$total.')')
            ->greeting('Grazie, il tuo pagamento è arrivato!')
            ->line('**Importo pagato:** '.$total);

        foreach ($order->items as $item) {
            $quantity = (int) ($item['quantity'] ?? 1);
            $mail->line('• '.($quantity > 1 ? $quantity.' × ' : '').($item['title'] ?? 'Articolo'));
        }

        $mail->line('🛡️ **Sei protetto:** i soldi restano custoditi da Hub Core e '.$this->tenant->name.' li riceve solo dopo la consegna.'
            .($order->release_at ? ' Se non ci segnali problemi, vengono consegnati al venditore il '.$order->release_at->timezone(config('app.timezone'))->locale('it')->translatedFormat('d F Y').'.' : ''))
            ->action('Vedi il tuo ordine', route('protected.order.show', $order->buyer_token))
            ->line('Se qualcosa non va, scrivici prima di quella data e blocchiamo il pagamento.')
            ->salutation('Hub Core');

        return $mail;
    }
}
