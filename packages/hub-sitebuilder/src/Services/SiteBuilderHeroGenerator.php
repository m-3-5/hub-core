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
