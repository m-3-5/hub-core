<?php

namespace M35\HubSitebuilder\Services;

use App\Models\Tenant;
use App\Services\GeminiTextClient;
use App\Support\BrandFonts;
use Illuminate\Support\Str;
use Throwable;

/**
 * L'IA (Gemini) scrive i testi del sito partendo da poche parole dell'attività.
 * Restituisce solo proposte: l'utente le vede, le cambia e poi pubblica (il controllo SiteGuard resta alla pubblicazione).
 */
class SiteCopyWriter
{
    public function __construct(private readonly GeminiTextClient $gemini) {}

    /**
     * @return array{tagline: string, services: array<int, string>, about: string, cta_label: string, extras: array<string, mixed>}|null  null se l'IA non risponde
     */
    public function write(Tenant $tenant, string $description, ?string $city = null): ?array
    {
        if (! $this->gemini->isConfigured()) {
            return null;
        }

        $kind = $tenant->type === 'ente' ? 'ente o associazione' : 'attività';
        $where = $city ? "Città/zona: {$city}." : '';
        $styles = implode(', ', array_keys(BrandFonts::PRESETS));

        $prompt = <<<PROMPT
Scrivi i testi del sito vetrina di una piccola {$kind} italiana.
Nome: "{$tenant->name}". {$where}
Cosa fa, con le parole del titolare: "{$description}"

Rispondi SOLO con un JSON con queste chiavi:
- "tagline": frase principale per la prima pagina, max 80 caratteri, concreta e calda, senza punto esclamativo, senza il nome.
- "services": da 3 a 6 servizi o prodotti principali, ognuno un oggetto {"name": max 40 caratteri, "line": una frase che lo spiega, max 90 caratteri, "icon": UNA sola emoji adatta}.
- "strengths": esattamente 3 punti di forza, ognuno {"title": max 30 caratteri, "text": max 90 caratteri, "icon": UNA sola emoji}. Solo cose ricavabili dalla descrizione, niente promesse inventate.
- "about": testo "Chi siamo" di 2-3 frasi brevi (max 350 caratteri), in prima persona plurale.
- "faq": da 3 a 4 domande frequenti che un cliente farebbe, ognuna {"q": max 80 caratteri, "a": max 200 caratteri}. Se la risposta dipende dal titolare (prezzi esatti, orari) invita a contattare.
- "closing": frase finale che invita a contattare, max 90 caratteri.
- "cta_label": testo del pulsante principale, max 25 caratteri (es. "Chiamaci", "Prenota ora", "Richiedi un preventivo").
- "style": lo stile grafico più adatto tra: {$styles}.
Regole: italiano semplice; niente link, email, numeri di telefono, indirizzi; non inventare anni di attività, premi, numeri, recensioni o certificazioni.
PROMPT;

        try {
            $raw = $this->gemini->generate($prompt, json: true, temperature: 0.7, timeout: 30, maxModels: 3);
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        $data = json_decode($this->stripFences($raw), true);

        if (! is_array($data)) {
            return null;
        }

        $tagline = self::clean($data['tagline'] ?? '', 200);
        $details = SiteContent::serviceDetails($data['services'] ?? []);

        if ($tagline === '' || $details === []) {
            return null;
        }

        return [
            'tagline' => $tagline,
            'services' => array_column($details, 'name'),
            'about' => self::clean($data['about'] ?? '', 1000),
            'cta_label' => self::clean($data['cta_label'] ?? '', 60) ?: 'Contattaci',
            // Testi in più per una pagina ricca: puliti allo stesso modo anche al salvataggio.
            'extras' => SiteContent::extras([
                'service_details' => $details,
                'strengths' => $data['strengths'] ?? [],
                'faq' => $data['faq'] ?? [],
                'closing' => $data['closing'] ?? '',
                'style' => $data['style'] ?? '',
            ]),
        ];
    }

    public static function clean(mixed $value, int $max): string
    {
        return Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) $value)) ?? ''), $max, '');
    }

    private function stripFences(string $raw): string
    {
        $raw = trim($raw);
        $raw = preg_replace('/^```(?:json)?\s*/i', '', $raw) ?? $raw;

        return preg_replace('/\s*```$/', '', $raw) ?? $raw;
    }
}
