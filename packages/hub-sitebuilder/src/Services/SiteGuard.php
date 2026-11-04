<?php

namespace M35\HubSitebuilder\Services;

use App\Models\Tenant;
use App\Services\ContentModerator;

/**
 * Filtro dei siti creati con Max: solo attività serie. Niente spam, volgarità, truffe o cose vietate.
 * Se il controllo automatico non è raggiungibile il sito NON va online (l'utente riprova tra poco).
 */
class SiteGuard
{
    public function __construct(private readonly ContentModerator $moderator) {}

    /**
     * @param  array<string, mixed>  $answers  tagline, services[], cta_label, extra
     * @return array{ok: bool, unavailable: bool, reason: string}
     */
    public function check(Tenant $tenant, array $answers): array
    {
        $text = trim(implode("\n", array_filter([
            'Nome attività: '.$tenant->name,
            'Frase principale: '.($answers['tagline'] ?? ''),
            'Servizi: '.implode(', ', (array) ($answers['services'] ?? [])),
            'Pulsante: '.($answers['cta_label'] ?? ''),
            'Altro: '.($answers['extra'] ?? ''),
            // Anche i testi in più scritti dall'IA (l'utente potrebbe averli cambiati prima di pubblicare).
            self::extrasText($answers['extras'] ?? null),
        ])));

        // Regola semplice anti-spam: un sito vetrina non ha bisogno di più di un link nei testi.
        if (preg_match_all('~(?:https?://|www\.)\S+~i', $text) > 1) {
            return ['ok' => false, 'unavailable' => false, 'reason' => 'Nei testi del sito c\'è più di un link: togli i link, i contatti si mostrano da soli.'];
        }

        $result = $this->moderator->check($text);

        return [
            'ok' => $result['allowed'],
            'unavailable' => $result['unavailable'],
            'reason' => $result['reason'],
        ];
    }

    private static function extrasText(?array $extras): string
    {
        if (! $extras) {
            return '';
        }

        $lines = [];

        foreach ($extras['service_details'] ?? [] as $s) {
            $lines[] = $s['name'].': '.$s['line'];
        }
        foreach ($extras['strengths'] ?? [] as $s) {
            $lines[] = $s['title'].': '.$s['text'];
        }
        foreach ($extras['faq'] ?? [] as $f) {
            $lines[] = $f['q'].' '.$f['a'];
        }
        $lines[] = $extras['closing'] ?? '';

        return 'Altri testi: '.implode("\n", array_filter($lines));
    }
}
