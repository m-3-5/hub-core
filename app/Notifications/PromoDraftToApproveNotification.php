<?php

namespace App\Notifications;

use App\Models\Promo;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class PromoDraftToApproveNotification extends Notification
{
    public function __construct(private readonly Promo $promo) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tenant = $this->promo->tenant;

        $mail = (new MailMessage)
            ->from(config('mail.from.address'), 'Hub Core - '.$tenant->name)
            ->subject('Nuova promo da approvare: '.$this->promo->title)
            ->greeting('Nuova promo da approvare')
            ->line('Dal tuo post '.($this->promo->importedFromLabel() ?? 'online').' abbiamo preparato una bozza: **'.$this->promo->title.'**.');

        if ($image = $this->promo->imageUrl()) {
            $mail->line(new HtmlString('<img src="'.e($image).'" alt="'.e($this->promo->title).'" style="max-width:100%;height:auto;border-radius:12px">'));
        }

        $mail->line('Non è ancora pubblica. Aprila, controllala e premi «Approva e pubblica»; se non va bene puoi modificarla o eliminarla.')
            ->action('Apri la promo', route('admin.promos.show', [$tenant, $this->promo]))
            ->salutation('Hub Core');

        if ($monitor = config('mail.leads_monitor_email')) {
            $mail->bcc($monitor);
        }

        return $mail;
    }
}
