<?php

namespace M35\HubPayments\Notifications;

use App\Models\Tenant;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use M35\HubPayments\Models\PayableOrder;

/** Al venditore: il compratore ha segnalato un problema, il pagamento resta fermo finché non è risolto. */
class ProtectedProblemNotification extends Notification
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
        $total = number_format($o->amount_cents / 100, 2, ',', '.').' €';

        $mail = (new MailMessage)
            ->from(config('mail.from.address'), 'Hub Core - '.$this->tenant->name)
            ->subject('Segnalazione su un ordine pagato — #'.$o->id.' ('.$total.')')
            ->greeting('Un cliente ha segnalato un problema')
            ->line('Ordine #'.$o->id.' · '.$total)
            ->line('**Cosa dice il cliente:**')
            ->line('«'.$o->problem_message.'»');

        $mail->line($o->isFrozen()
            ? 'Il pagamento è **fermo**: non verrà girato finché il problema non è risolto.'
            : 'I soldi erano già stati girati: il team Hub Core ti contatterà per chiarire.');

        if ($o->customer_email) {
            $mail->line('Puoi rispondere direttamente al cliente: '.$o->customer_email.' '.($o->customer_phone ? '· '.$o->customer_phone : ''));
            $mail->replyTo($o->customer_email, $o->customer_name ?: null);
        }

        return $mail
            ->line('Risolvere in fretta (consegna, sostituzione, accordo con il cliente) sblocca il pagamento. Se la segnalazione è fondata e non viene risolta, il cliente viene rimborsato e il costo è a tuo carico, come da condizioni economiche.')
            ->action('Condizioni economiche', route('terms.economic'))
            ->salutation('Hub Core');
    }
}
