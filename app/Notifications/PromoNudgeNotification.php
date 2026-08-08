<?php

namespace App\Notifications;

use App\Models\Tenant;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class PromoNudgeNotification extends Notification
{
    public function __construct(
        private readonly Tenant $tenant,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $promoUrl = url(route('admin.promos.create', $this->tenant));
        $feedbackUrl = URL::temporarySignedRoute(
            'feedback.show',
            now()->addDays(30),
            ['tenant' => $this->tenant->slug, 'c' => 'promo-nudge-'.now()->format('Y-m')],
        );

        return (new MailMessage)
            ->subject('🎁 Un mese di promo omaggio ti aspetta, '.$this->tenant->name)
            ->greeting('Ciao '.$this->tenant->name.'!')
            ->line('Le promo pubblicate su Hub Core sono il modo più veloce per farti notare da chi cerca proprio quello che offri — e oggi abbiamo un motivo in più per provarle.')
            ->line('**Ti abbiamo aggiunto una promo extra gratuita** questo mese, oltre a quelle già incluse nel tuo piano. Bastano due minuti per crearla: carichi un\'immagine (o la generiamo noi), scrivi due righe, e Max pensa al resto — titolo, descrizione e persino un volantino o un breve video pronti da condividere.')
            ->action('Crea la tua promo omaggio', $promoUrl)
            ->line('Se hai già provato Hub Core, dicci come va: due minuti di feedback ci aiutano a migliorare l\'app proprio in base a quello che usi davvero.')
            ->line('[Lascia un feedback veloce]('.$feedbackUrl.')')
            ->salutation('A presto, il team Hub Core');
    }
}
