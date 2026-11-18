<?php

namespace App\Notifications;

use App\Models\Tenant;
use App\Models\TenantUsageEntry;
use App\Support\PayPerUse;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Ricevuta (non fiscale) di un acquisto o di una ricarica del credito: al cliente e a Max. */
class UsageReceiptNotification extends Notification
{
    public function __construct(
        private readonly Tenant $tenant,
        private readonly TenantUsageEntry $entry,
        private readonly int $creditCents,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public static function number(TenantUsageEntry $entry): string
    {
        return $entry->created_at->format('Y').'/'.str_pad((string) $entry->id, 6, '0', STR_PAD_LEFT);
    }

    public static function paidWith(TenantUsageEntry $entry): string
    {
        return match (true) {
            $entry->reason === 'gift' => 'Regalo di Hub Core',
            $entry->reason === 'topup' => str_replace(['Ricarica del credito (', ')'], '', $entry->description),
            $entry->status === 'credit' => 'Credito prepagato',
            $entry->status === 'owed' => 'Fido: da saldare con una ricarica',
            in_array($entry->status, ['pending', 'failed'], true) => 'Carta salvata, addebito il '.$entry->bill_after?->timezone(config('app.timezone'))->format('d/m/Y'),
            $entry->status === 'billed' => 'Carta salvata',
            default => '—',
        };
    }

    public function toMail(object $notifiable): MailMessage
    {
        $co = config('company');
        $isTopUp = $this->entry->reason === 'topup';
        $date = $this->entry->created_at->timezone(config('app.timezone'))->format('d/m/Y H:i');
        $name = $notifiable->name ?? null;

        return (new MailMessage)
            ->subject('Ricevuta n. '.self::number($this->entry).' — '.($isTopUp ? 'ricarica del credito' : 'acquisto').' — '.$this->tenant->name)
            ->greeting($name ? 'Ciao, '.$name.'!' : 'Ciao!')
            ->line($isTopUp ? 'Grazie, la ricarica è arrivata.' : 'Ecco la ricevuta di quello che hai appena attivato su Hub Core.')
            ->line('**Ricevuta n. '.self::number($this->entry).'** del '.$date)
            ->line('Attività: **'.$this->tenant->name.'**')
            ->line('Descrizione: '.$this->entry->description)
            ->line('Importo: **'.PayPerUse::euro((int) $this->entry->amount_cents).'**')
            ->line('Pagato con: '.self::paidWith($this->entry))
            ->line('Credito rimasto: **'.PayPerUse::euro($this->creditCents).'**'.($this->creditCents < 0 ? ' (stai usando il fido)' : ''))
            ->action('Vedi credito e movimenti', route('admin.credit.show', $this->tenant))
            ->line('Emessa da '.$co['name'].' · P.IVA '.$co['vat'].' · '.$co['seat']['street'].', '.$co['seat']['zip'].' '.$co['seat']['city'].' ('.$co['seat']['province'].').')
            ->line('_Documento non fiscale. Se ti serve la fattura, scrivi a '.$co['email'].'._')
            ->salutation('Il team Hub Core');
    }
}
