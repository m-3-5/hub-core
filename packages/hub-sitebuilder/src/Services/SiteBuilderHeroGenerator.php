<?php

namespace M35\HubSitebuilder\Services;

use App\Models\Tenant;
use App\Services\FlyerVideoGenerator;
use App\Services\GeminiTextClient;
use App\Services\TenantBrandManager;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SiteBuilderHeroGenerator
{
    /** @var array<string, string> */
    private const STYLE_DIRECTIVES = [
        'elegante' => 'Stile elegante ed emozionale: curve morbide e organiche, texture leggera, palette con più tonalità del colore brand, nessun testo.',
        'moderno' => 'Stile moderno e diretto: blocchi geometrici puliti disposti in modo asimmetrico, forte contrasto tra il colore brand e il bianco/nero, nessun testo.',
        'amichevole' => 'Stile amichevole e caldo: forme arrotondate morbide, palette calda e invitante, piccole illustrazioni semplici a linea, nessun testo.',
        'tech' => 'Stile tech e professionale: forme geometriche nette (esagoni, triangoli, linee sottili), gradiente dal colore brand verso un tono più scuro, nessun testo.',
        'classico' => 'Stile classico e istituzionale: composizione simmetrica, cornice sottile decorativa, palette sobria, nessun testo.',
        'digitale' => 'Stile digitale/software-house: sfondo scuro quasi nero con un bagliore sfumato del colore brand, una sottile "costellazione" di punti collegati da linee filiformi, forme triangolari/geometriche nette, nessun testo.',
    ];

    public function __construct(
        private GeminiTextClient $gemini,
        private TenantBrandManager $brand,
        private FlyerVideoGenerator $videoGenerator,
    ) {}

    /**
     * Generates a pure decorative hero artwork (no baked-in text — the real headline
     * is real HTML on top, editable/SEO-friendly) in the tenant's brand style, then
     * turns it into a slow-zoom looping background video, reusing the same free
     * Gemini-text + Imagick pipeline already built for promo flyers.
     *
     * @return array{svg_path: string, video_path: ?string}|null
     */
    public function generate(Tenant $tenant, string $directory, ?string $style = null): ?array
    {
        $apiKey = config('services.gemini.api_key');

        if (! $apiKey) {
            return null;
        }

        if (function_exists('set_time_limit')) {
            set_time_limit(90);
        }

        $color = $tenant->primary_color ?: '#6366f1';
        $fontKey = $style ?: $this->brand->font($tenant);
        $styleDirective = self::STYLE_DIRECTIVES[$fontKey] ?? self::STYLE_DIRECTIVES['moderno'];

        $prompt = <<<PROMPT
Genera SOLO il codice SVG (nessun markdown, nessuna spiegazione, inizia direttamente con <svg) di un'illustrazione decorativa astratta 1600x900px per lo sfondo hero del sito web di "{$tenant->name}".
Colore principale del brand: {$color} — usa un gradiente (linearGradient o radialGradient) con 2-3 tonalità derivate da questo colore.
Direzione artistica: {$styleDirective}
Deve essere puramente decorativa: NESSUN testo, NESSuna scritta, NESSUN logo — solo forme, linee, texture artistiche. Non banale (niente semplici due macchie sfumate + puntini): almeno 5-6 elementi grafici distinti che diano profondità e movimento visivo.
PROMPT;

        try {
            // Stesso client delle altre funzioni IA: modelli di ripiego se uno è esaurito o sovraccarico.
            $text = $this->gemini->generate($prompt, temperature: 0.9, timeout: 60, maxModels: 2);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }

        $svg = $this->extractSvg($text);

        return $svg ? $this->save($svg, $directory) : null;
    }

    /**
     * Disegno moderno a tema per la parte in alto (accanto al titolo), uguale per tutti i siti:
     * oggetti riconoscibili del settore, colori del sito, sfondo trasparente, nessun testo.
     * Gratis (Gemini testo). Restituisce il percorso dell'SVG salvato, o null.
     *
     * @param  array<string, mixed>  $answers
     */
    public function illustration(Tenant $tenant, array $answers, string $directory): ?string
    {
        if (! config('services.gemini.api_key')) {
            return null;
        }

        if (function_exists('set_time_limit')) {
            set_time_limit(300);
        }

        $color = $tenant->primary_color ?: '#6366f1';
        $services = implode(', ', array_slice(array_map('strval', (array) ($answers['services'] ?? [])), 0, 6));
        $tagline = Str::limit((string) ($answers['tagline'] ?? ''), 160);
        $about = Str::limit((string) ($answers['about'] ?? ''), 300);

        $prompt = <<<PROMPT
Genera SOLO il codice SVG (nessun markdown, nessuna spiegazione, inizia direttamente con <svg) di un'illustrazione SEMPLICE e moderna in stile flat (poche forme pulite, come le icone grandi delle app), per la parte in alto del sito di "{$tenant->name}".
Cosa fa l'attività: {$tagline}. {$about}
Servizi: {$services}.
Regole:
- Attributi obbligatori: xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 640". Sfondo TRASPARENTE: nessun rettangolo che copre tutto.
- Composizione semplice: UNA grande forma morbida di sfondo (cerchio o blob) e sopra 3 o 4 oggetti del settore, grandi e ben riconoscibili, disegnati con poche forme geometriche (es. autoscuola: auto, segnale stradale, tessera patente; dentista: dente sorridente, spazzolino; parrucchiere: forbici, phon, pettine; ufficio pratiche: cartella, timbro, chiavi dell'auto). Al massimo 4-5 puntini o scintille decorative. Niente dettagli minuti. Tutto deve stare DENTRO il viewBox con almeno 40 px di margine (niente forme tagliate ai bordi) e gli oggetti non si sovrappongono troppo.
- Palette: il colore del sito {$color} con 2-3 sue varianti più chiare e più scure, bianco, e UN colore accento caldo che stia bene con {$color}. Gradienti morbidi (linearGradient), ombre leggere con opacity, angoli arrotondati, contorni puliti.
- NESSUN testo, lettera, numero, logo, marchio o persona. Niente <image>, <foreignObject>, <script>, link esterni, font.
- Codice compatto (massimo circa 8 KB), solo forme: path, rect, circle, ellipse, polygon, g, defs, linearGradient, radialGradient, stop.
PROMPT;

        // Due tentativi; in ognuno si provano tutti i modelli (quelli occupati o senza quota rispondono subito di no).
        $svg = null;
        for ($attempt = 1; $attempt <= 2 && ! $svg; $attempt++) {
            try {
                $text = $this->gemini->generate($prompt, temperature: 0.8, timeout: 45);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Disegno del sito: IA non disponibile', ['tenant' => $tenant->slug, 'message' => $e->getMessage()]);

                continue;
            }

            $candidate = $this->extractSvg($text);
            if ($candidate && strlen($candidate) <= 120_000 && ! preg_match('/<image|<foreignObject|url\(\s*["\']?https?:/i', $candidate) && $this->wellFormed($candidate)) {
                $svg = $candidate;
            } else {
                \Illuminate\Support\Facades\Log::warning('Disegno del sito: SVG non valido, riprovo', ['tenant' => $tenant->slug, 'attempt' => $attempt]);
            }
        }

        if (! $svg) {
            return null;
        }

        Storage::disk('public')->makeDirectory($directory);
        $path = $directory.'/art-'.Str::random(20).'.svg';
        Storage::disk('public')->put($path, $svg);

        return $path;
    }

    private function wellFormed(string $svg): bool
    {
        $previous = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($svg, 'SimpleXMLElement', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $doc !== false && strtolower($doc->getName()) === 'svg';
    }

    private function extractSvg(string $raw): ?string
    {
        $raw = trim($raw);
        $raw = preg_replace('/^```(?:svg|xml)?\s*/i', '', $raw) ?? $raw;
        $raw = preg_replace('/\s*```\s*$/', '', $raw) ?? $raw;

        if (! preg_match('/<svg\b[^>]*>.*<\/svg>/is', $raw, $match)) {
            return null;
        }

        $svg = trim($match[0]);

        if (preg_match('/<script|onload=|javascript:|href\s*=\s*["\']https?:/i', $svg)) {
            return null;
        }

        return $svg;
    }

    /** @return array{svg_path: string, video_path: ?string} */
    private function save(string $svg, string $directory): array
    {
        Storage::disk('public')->makeDirectory($directory);
        $svgPath = $directory.'/'.Str::random(24).'.svg';
        Storage::disk('public')->put($svgPath, $svg);

        $video = $this->videoGenerator->generateFromSvg(
            Storage::disk('public')->path($svgPath),
            $directory,
        );

        return [
            'svg_path' => $svgPath,
            'video_path' => $video['path'] ?? null,
        ];
    }
}
