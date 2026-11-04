<?php

namespace M35\HubSitebuilder\Services;

use App\Models\Tenant;
use App\Services\GeminiTextClient;
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
     * @return array{tagline: string, services: array<int, string>, about: string, cta_label: string}|null  null se l'IA non risponde
     */
    public function write(Tenant $tenant, string $description, ?string $city = null): ?array
    {
        if (! $this->gemini->isConfigured()) {
            return null;
        }

        $kind = $tenant->type === 'ente' ? 'ente o associazione' : 'attività';
        $where = $city ? "Città/zona: {$city}." : '';

        $prompt = <<<PROMPT
Scrivi i testi del sito vetrina di una piccola {$kind} italiana.
Nome: "{$tenant->name}". {$where}
Cosa fa, con le parole del titolare: "{$description}"

Rispondi SOLO con un JSON con queste chiavi:
- "tagline": frase principale per la prima pagina, max 80 caratteri, concreta e calda, senza punto esclamativo, senza il nome.
- "services": da 3 a 6 servizi o prodotti principali, ognuno max 40 caratteri.
- "about": testo "Chi siamo" di 2-3 frasi brevi (max 350 caratteri), in prima persona plurale, senza inventare anni, premi, numeri o indirizzi.
- "cta_label": testo del pulsante principale, max 25 caratteri (es. "Chiamaci", "Prenota ora", "Richiedi un preventivo").
Italiano semplice, niente link, niente email, niente numeri di telefono, niente emoji.
PROMPT;

        try {
            $raw = $this->gemini->generate($prompt, json: true, temperature: 0.7, timeout: 25, maxModels: 3);
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        $data = json_decode($this->stripFences($raw), true);

        if (! is_array($data)) {
            return null;
        }

        $clean = fn ($v, int $max) => Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) $v)) ?? ''), $max, '');

        $services = collect(is_array($data['services'] ?? null) ? $data['services'] : explode(',', (string) ($data['services'] ?? '')))
            ->map(fn ($s) => $clean(is_array($s) ? ($s['name'] ?? '') : $s, 60))
            ->map(fn ($s) => trim(preg_replace('/\s+/', ' ', str_replace(',', ' ', $s)) ?? ''))
            ->filter()
            ->unique()
            ->take(8)
            ->values()
            ->all();

        $tagline = $clean($data['tagline'] ?? '', 200);

        if ($tagline === '' || $services === []) {
            return null;
        }

        return [
            'tagline' => $tagline,
            'services' => $services,
            'about' => $clean($data['about'] ?? '', 1000),
            'cta_label' => $clean($data['cta_label'] ?? '', 60) ?: 'Contattaci',
        ];
    }

    private function stripFences(string $raw): string
    {
        $raw = trim($raw);
        $raw = preg_replace('/^```(?:json)?\s*/i', '', $raw) ?? $raw;

        return preg_replace('/\s*```$/', '', $raw) ?? $raw;
    }
}
