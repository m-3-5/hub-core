<?php

namespace App\Support;

use App\Models\Tenant;

class FeedbackQuestions
{
    /**
     * Domande diverse in base a cosa il tenant ha effettivamente creato/usato,
     * così il feedback è specifico e utile per migliorare le parti dell'app
     * che quella persona ha davvero toccato, non un questionario generico.
     *
     * @return array<int, array{key: string, label: string, type: string, options?: array<int, string>}>
     */
    public static function forTenant(Tenant $tenant): array
    {
        $questions = [];

        $promoCount = $tenant->promos()->count();
        $hasVideo = $tenant->promos()
            ->get()
            ->contains(fn ($p) => ! empty($p->image_variants['video'] ?? null));
        $hasGeneratedSite = class_exists(\M35\HubSitebuilder\Models\GeneratedSite::class)
            && $tenant->generatedSite()->exists();
        $hasServices = class_exists(\M35\HubPayments\Models\PayableService::class)
            && $tenant->payableServices()->exists();

        if ($promoCount > 0) {
            $questions[] = [
                'key' => 'promo_ease',
                'label' => 'Quanto è stato facile creare la tua prima promo?',
                'type' => 'scale',
            ];
            $questions[] = [
                'key' => 'promo_improve',
                'label' => 'Cosa cambieresti nel modo in cui si crea una promo?',
                'type' => 'text',
            ];
        } else {
            $questions[] = [
                'key' => 'promo_blocker',
                'label' => 'Non hai ancora creato una promo — cosa te lo ha impedito o cosa ti manca per iniziare?',
                'type' => 'text',
            ];
        }

        if ($hasVideo) {
            $questions[] = [
                'key' => 'video_quality',
                'label' => 'Hai provato il video automatico al posto del volantino statico — che ne pensi?',
                'type' => 'text',
            ];
        }

        if ($hasGeneratedSite) {
            $questions[] = [
                'key' => 'sitebuilder_quality',
                'label' => 'Come valuti il sito che hai creato con Max?',
                'type' => 'scale',
            ];
        }

        if ($hasServices) {
            $questions[] = [
                'key' => 'services_ease',
                'label' => 'Come valuti la gestione dei tuoi servizi a pagamento?',
                'type' => 'scale',
            ];
        }

        $questions[] = [
            'key' => 'overall_rating',
            'label' => 'In generale, quanto sei soddisfatto/a di Hub Core finora?',
            'type' => 'scale',
        ];

        $questions[] = [
            'key' => 'missing_feature',
            'label' => 'Cosa manca ancora all\'app per esserti davvero utile?',
            'type' => 'text',
        ];

        return $questions;
    }
}
