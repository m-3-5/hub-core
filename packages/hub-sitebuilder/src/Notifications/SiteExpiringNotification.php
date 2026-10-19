<?php

namespace M35\HubSitebuilder\Notifications;

use App\Models\Tenant;
use Carbon\CarbonInterface;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Avviso prima della rimozione del sito: chi paga o lo usa ancora può tenerlo. */
class SiteExpiringNotification extends Notification
{
    public function __construct(private readonly Tenant $tenant, private readonly string $reasonLabel, private readonly CarbonInterface $deleteAfter, private readonly bool $payment) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->from(config('mail.from.address'), 'Hub Core - '.$this->tenant->name)
            ->subject('Il tuo sito sarà rimosso il '.$this->deleteAfter->format('d/m/Y'))
            ->greeting('Vuoi tenere il sito di '.$this->tenant->name.'?')
            ->line('Motivo dell\'avviso: '.$this->reasonLabel.'.')
            ->line('Se non fai nulla, il **'.$this->deleteAfter->format('d/m/Y').'** il sito non sarà più online e verrà cancellato.');

        $this->payment
            ? $mail->line('Per tenerlo basta attivare o regolarizzare l\'abbonamento.')->action('Sistema l\'abbonamento', route('admin.billing.show', $this->tenant))
            : $mail->line('Per tenerlo basta aprirlo e salvarlo di nuovo: così sappiamo che lo usi.')->action('Apri il sito', route('admin.sitebuilder.show', $this->tenant));

        return $mail->salutation('Hub Core');
    }
}
