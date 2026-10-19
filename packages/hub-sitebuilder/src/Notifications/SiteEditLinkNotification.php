<?php

namespace M35\HubSitebuilder\Notifications;

use App\Models\Tenant;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use M35\HubSitebuilder\Models\GeneratedSite;

/** Link segreto per modificare il sito senza account: arriva solo alla email del titolare. */
class SiteEditLinkNotification extends Notification
{
    public function __construct(private readonly Tenant $tenant, private readonly string $token) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->from(config('mail.from.address'), 'Hub Core - '.$this->tenant->name)
            ->subject('Il tuo sito: modificalo quando vuoi')
            ->greeting('Il sito di '.$this->tenant->name.' è pronto')
            ->line('Puoi cambiare testi e pulsante del tuo sito quando vuoi, anche dal telefono e senza password.')
            ->action('Modifica il tuo sito', route('site.edit.show', $this->token))
            ->line('Il link è **solo tuo**: non inoltrarlo a nessuno. Vale '.GeneratedSite::EDIT_LINK_DAYS.' giorni e se ne chiedi uno nuovo quello vecchio smette di funzionare.')
            ->line('Il sito è online qui: '.route('site.public.show', $this->tenant))
            ->salutation('Hub Core');
    }
}
