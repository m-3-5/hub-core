<?php

namespace M35\HubPayments\Notifications;

use App\Models\Tenant;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use M35\HubPayments\Models\PayableOrder;

/** Al compratore: cosa è successo al suo ordine protetto (consegnato al venditore, segnalazione ricevuta, rimborso). */
class BuyerOrderUpdateNotification extends Notification
{
    public function __construct(
        private readonly Tenant $tenant,
        private readonly PayableOrder $order,
        private readonly string $kind, // released | problem | refunded
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $total = number_format($this->order->amount_cents / 100, 2, ',', '.').' €';
        $mail = (new MailMessage)->from(config('mail.from.address'), 'Hub Core - '.$this->tenant->name);

        [$subject, $greeting, $lines] = match ($this->kind) {
            'problem' => [
                'Abbiamo ricevuto la tua segnalazione — '.$this->tenant->name,
                'Segnalazione ricevuta',
                ['Il pagamento di '.$total.' resta **fermo**: '.$this->tenant->name.' non lo riceve finché il problema non è risolto.', 'Ti contatteremo noi o il venditore. Se non si risolve, ti rimborsiamo.'],
            ],
            'refunded' => [
                'Rimborso effettuato — '.$this->tenant->name,
                'Ti abbiamo rimborsato',
                ['Abbiamo rimborsato '.$total.' sulla carta con cui hai pagato.', 'I tempi di accredito dipendono dalla tua banca (di solito qualche giorno lavorativo).'],
            ],
            default => [
                'Pagamento consegnato al venditore — '.$this->tenant->name,
                'Ordine concluso',
                ['Il pagamento di '.$total.' è stato consegnato a '.$this->tenant->name.'.'.($this->order->confirmed_at ? ' Grazie per aver confermato.' : ' Non ci sono state segnalazioni nei giorni di attesa.'), 'Se hai ancora un problema, scrivici.'],
            ],
        };

        $mail->subject($subject)->greeting($greeting);

        foreach ($lines as $line) {
            $mail->line($line);
        }

        return $mail
            ->action('Vedi il tuo ordine', route('protected.order.show', $this->order->buyer_token))
            ->line('Condizioni di acquisto: '.route('terms.buyer'))
            ->salutation('Hub Core');
    }
}
