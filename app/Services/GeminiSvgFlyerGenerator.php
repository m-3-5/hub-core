<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GeminiSvgFlyerGenerator
{
    /** @var array<string, string> */
    private const STYLE_DIRECTIVES = [
        'elegante' => 'Stile elegante ed emozionale: curve morbide e organiche, texture leggera floreale o "grana" sottile, palette con più tonalità del colore brand (chiaro/scuro), tipografia raffinata con forte contrasto di peso tra titolo (bold) e sottotitolo (leggero, eventualmente corsivo).',
        'moderno' => 'Stile moderno e diretto: blocchi geometrici puliti disposti in modo asimmetrico, forte contrasto tra il colore brand e il bianco/nero, tipografia sans-serif bold, un elemento grafico decorativo (forma organica o geometrica) come punto focale.',
        'amichevole' => 'Stile amichevole e caldo: forme arrotondate morbide, palette calda e invitante, piccole illustrazioni semplici a linea (es. stelline, cuori, forme giocose) sparse con equilibrio, tipografia rotonda e accogliente.',
        'tech' => 'Stile tech e professionale: forme geometriche nette (esagoni, triangoli, linee sottili), gradiente dal colore brand verso un tono più scuro, dettagli a griglia o circuito leggero, tipografia squadrata e sicura.',
        'classico' => 'Stile classico e istituzionale: composizione simmetrica, cornice sottile decorativa, palette sobria (colore brand + crema/bianco), tipografia serif elegante, dettagli minimal e ordinati.',
        'digitale' => 'Stile digitale/software-house: sfondo scuro quasi nero con un bagliore sfumato del colore brand in un angolo, una sottile "costellazione" di punti collegati da linee filiformi, una o più forme triangolari/geometriche nette, etichette in stile monospace maiuscolo, titolo sans-serif bold molto leggibile.',
    ];

    public function __construct(
        private GeminiModelResolver $models,
        private TenantBrandManager $brand,
    ) {}

    /**
     * Writes a promo flyer as SVG (via Gemini text generation, no image-gen quota needed),
     * rasterizes to PNG when Imagick is available, and returns the storage path + mime.
     *
     * @return array{path: string, mime: string}|null
     */
    public function generate(Tenant $tenant, string $headline, ?string $subline, ?string $logoAbsolutePath, string $directory): ?array
    {
        $apiKey = config('services.gemini.api_key');

        if (! $apiKey) {
            return null;
        }

        if (function_exists('set_time_limit')) {
            set_time_limit(90);
        }

        $this->models->ensureDiscovered();

        $color = $tenant->primary_color ?: '#6366f1';
        $safeHeadline = Str::limit($headline, 60, '');
        $fontKey = $this->brand->font($tenant);
        $styleDirective = self::STYLE_DIRECTIVES[$fontKey] ?? self::STYLE_DIRECTIVES['moderno'];

        $prompt = <<<PROMPT
Genera SOLO il codice SVG (nessun markdown, nessuna spiegazione, inizia direttamente con <svg) di un volantino promozionale verticale 800x1200px per l'attività "{$tenant->name}".
Colore principale del brand: {$color} — usa un gradiente (linearGradient o radialGradient) con 2-3 tonalità derivate da questo colore come sfondo, non un riempimento piatto a tinta unita.
Direzione artistica da seguire: {$styleDirective}
Testo principale grande e leggibile: "{$safeHeadline}"
PROMPT;

        if ($subline) {
            $prompt .= "\nTesto secondario più piccolo sotto: \"".Str::limit($subline, 100, '')."\"";
        }

        $prompt .= "\n\nRegole di composizione obbligatorie:\n"
            ."- Layout con gerarchia visiva chiara: una piccola etichetta/eyebrow in alto, il titolo grande, il sottotitolo più piccolo, e almeno un elemento decorativo che faccia da punto focale (non simmetrico/centrato in modo banale).\n"
            ."- Almeno 4-5 elementi grafici distinti (forme, linee, texture), non ripetitivi.\n"
            ."- VIETATO lo schema banale \"due macchie/blob sfumati negli angoli opposti + tre pallini allineati al centro\": è già stato usato ed è troppo semplice, serve qualcosa di più elaborato e originale.\n"
            ."- Buon contrasto testo/sfondo, tipografia coerente con lo stile indicato sopra.\n"
            ."- NO loghi disegnati, NO testo illeggibile o sovrapposto, NO watermark, NO cornici bianche vuote enormi senza contenuto.\n\n"
            ."Regole tipografiche OBBLIGATORIE (fondamentali, non violarle mai):\n"
            ."- Margine di sicurezza di almeno 60px su ogni lato: nessun testo deve mai uscire dal canvas (x tra 60 e 740, considerando la larghezza del testo).\n"
            ."- Se il titolo è lungo, spezzalo su 2-3 righe usando più <tspan x=\"...\" dy=\"...\"> con lo stesso x, invece di scriverlo tutto su una riga sola.\n"
            ."- Scegli una dimensione font per il titolo (di solito tra 40 e 64px) tale che ogni singola riga, alla lunghezza del testo dato, resti dentro i margini di sicurezza: se non sei sicuro che entri, riduci il font-size o spezza su più righe.\n"
            ."- Usa in modo coerente text-anchor=\"middle\" con x=\"400\" per titolo/sottotitolo/etichette centrate, oppure text-anchor=\"start\" con x=\"60\" per testo allineato a sinistra — mai un misto che porti il testo fuori dai margini.";

        foreach ($this->models->textModels() as $model) {
            $response = Http::timeout(60)->post(
                "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}",
                ['contents' => [['parts' => [['text' => $prompt]]]]]
            );

            if (! $response->successful()) {
                continue;
            }

            $text = data_get($response->json(), 'candidates.0.content.parts.0.text');
            $svg = $this->extractSvg($text ?? '');

            if ($svg) {
                return $this->save($svg, $logoAbsolutePath, $directory);
            }
        }

        return null;
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

    /** @return array{path: string, mime: string} */
    private function save(string $svg, ?string $logoAbsolutePath, string $directory): array
    {
        if ($logoAbsolutePath && is_file($logoAbsolutePath)) {
            $logoMime = mime_content_type($logoAbsolutePath) ?: 'image/png';
            $logoData = 'data:'.$logoMime.';base64,'.base64_encode(file_get_contents($logoAbsolutePath));
            $logoTag = '<image href="'.$logoData.'" x="24" y="24" width="90" height="90" preserveAspectRatio="xMidYMid meet"/>';
            $svg = preg_replace('/(<svg\b[^>]*>)/', '$1'.$logoTag, $svg, 1) ?? $svg;
        }

        Storage::disk('public')->makeDirectory($directory);
        $svgPath = $directory.'/'.Str::random(24).'.svg';
        Storage::disk('public')->put($svgPath, $svg);

        $pngPath = $this->tryRasterize($svg, $directory);

        return $pngPath
            ? ['path' => $pngPath, 'mime' => 'image/png']
            : ['path' => $svgPath, 'mime' => 'image/svg+xml'];
    }

    private function tryRasterize(string $svg, string $directory): ?string
    {
        if (! extension_loaded('imagick')) {
            return null;
        }

        try {
            $imagick = new \Imagick();
            $imagick->setBackgroundColor(new \ImagickPixel('transparent'));
            $imagick->readImageBlob($svg);
            $imagick->setImageFormat('png32');

            $path = $directory.'/'.Str::random(24).'.png';
            Storage::disk('public')->put($path, $imagick->getImageBlob());
            $imagick->destroy();

            return $path;
        } catch (\Throwable) {
            return null;
        }
    }
}
