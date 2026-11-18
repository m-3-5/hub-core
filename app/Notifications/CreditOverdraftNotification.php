<?php

namespace App\Notifications;

use App\Models\Tenant;
use App\Support\PayPerUse;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Il credito è finito: si continua col fido e si chiede una ricarica. Al cliente e a Max. */
class CreditOverdraftNotification extends Notification
{
    public function __construct(private readonly Tenant $tenant, private readonly int $creditCents) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $notifiable->name ?? null;
        $left = PayPerUse::overdraftCents() + $this->creditCents;

        return (new MailMessage)
            ->subject('Credito finito: ricarica per continuare — '.$this->tenant->name)
            ->greeting($name ? 'Ciao, '.$name.'!' : 'Ciao!')
            ->line('Il credito di **'.$this->tenant->name.'** è finito. Per non fermarti abbiamo pubblicato lo stesso: stai usando il fido (credito ora **'.PayPerUse::euro($this->creditCents).'**, puoi andare avanti ancora per '.PayPerUse::euro(max(0, $left)).').')
            ->line('Per continuare scegli tu come:')
            ->line('• **Ricarica** da '.PayPerUse::euro(PayPerUse::topUpMinCents()).' in su con la carta (paghi subito, una volta);')
            ->line('• **Salva la carta**: paghi quello che pubblichi dopo 30 giorni, tutto insieme;')
            ->line('• **Bonifico o contanti**: scrivi a '.config('company.email').' e lo segniamo noi.')
            ->action('Ricarica il credito', route('admin.credit.show', $this->tenant))
            ->salutation('Il team Hub Core');
    }
}
