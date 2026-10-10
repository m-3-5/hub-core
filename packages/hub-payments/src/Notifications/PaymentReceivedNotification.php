<?php

namespace M35\HubPayments\Notifications;

use App\Models\Tenant;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use M35\HubPayments\Models\PayableOrder;

/** Email al titolare quando un cliente paga (carrello o preventivo): articoli, importo e dati del cliente. */
class PaymentReceivedNotification extends Notification
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
        $total = $this->money($order->amount_cents, $order->currency);
        $isQuote = collect($order->items)->contains(fn ($item) => ($item['type'] ?? null) === 'quote');

        $mail = (new MailMessage)
            ->from(config('mail.from.address'), 'Hub Core - '.$this->tenant->name)
            ->subject(($isQuote ? 'Preventivo pagato' : 'Nuovo ordine pagato').' — '.$total)
            ->greeting($isQuote ? 'Un preventivo è stato pagato!' : 'Hai ricevuto un pagamento!')
            ->line('**Importo:** '.$total);

        $mail->line('**'.($isQuote ? 'Preventivo' : 'Articoli').':**');

        foreach ($order->items as $item) {
            $quantity = (int) ($item['quantity'] ?? 1);
            $line = ($quantity > 1 ? $quantity.' × ' : '').($item['title'] ?? 'Voce');
            $unit = $this->money((int) ($item['unit_amount_cents'] ?? 0), $order->currency);

            $mail->line('• '.$line.' — '.$unit.($quantity > 1 ? ' cad.' : ''));
        }

        $mail->line('**Dati del cliente:**');
        $mail->line('Nome: '.($order->customer_name ?: 'non indicato'));
        $mail->line('Email: '.($order->customer_email ?: 'non indicata'));
        $mail->line('Telefono: '.($order->customer_phone ?: 'non indicato'));

        $mail->line('Canale: '.($order->channel === 'hub' ? 'inm35.it' : 'sito '.($this->tenant->name)).' · Pagato il '.$order->paid_at?->timezone(config('app.timezone'))->format('d/m/Y H:i'))
            ->line($order->isProtected()
                ? '🛡️ **Pagamento protetto:** i soldi sono custoditi da Hub Core e ti vengono girati quando il cliente conferma di aver ricevuto, '
                    .'oppure il '.($order->release_at?->timezone(config('app.timezone'))->locale('it')->translatedFormat('d F Y') ?? 'dopo la scadenza').' se non ci sono segnalazioni. '
                    .'Riceverai l\'importo meno la commissione di Stripe (circa '.$this->money(\M35\HubPayments\Support\StripeFees::estimateCents($order->amount_cents), $order->currency).'). Contatta il cliente per ritiro o prenotazione e consegna con puntualità.'
                : 'Il pagamento è già sul tuo conto Stripe. Contatta il cliente per ritiro o prenotazione.')
            ->salutation('Hub Core');

        if ($order->customer_email) {
            $mail->replyTo($order->customer_email, $order->customer_name ?: null);
        }

        if ($monitor = config('mail.leads_monitor_email')) {
            $mail->bcc($monitor);
        }

        return $mail;
    }

    private function money(int $cents, string $currency): string
    {
        $symbol = strtolower($currency) === 'eur' ? '€' : strtoupper($currency);

        return number_format($cents / 100, 2, ',', '.').' '.$symbol;
    }
}
