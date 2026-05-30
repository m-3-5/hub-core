<?php

namespace App\Services;

use App\Models\Promo;
use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiThemeIconGenerator
{
    /** @var array<string, string> */
    private array $themes = [
        'hair' => 'parrucchiere: profilo di donna in silhouette con capelli lunghi morbidi e ondulati che fluiscono all\'indietro, stile logo elegante da salone di bellezza',
        'body' => 'trattamenti corpo: silhouette femminile elegante e armoniosa, corpo intero stilizzato, stile wellness spa minimalista',
        'nails' => 'manicure e unghie: mano femminile delicata con unghie curate, stile beauty salon raffinato',
        'spa' => 'benessere e viso: profilo donna serena con tratti soft, stile centro estetico relax',
        'beauty' => 'centro bellezza: profilo donna con capelli fluidi e dettaglio fiore stilizzato, logo beauty premium',
        'mirror' => 'trucco e stile: profilo donna con specchio elegante stilizzato, look fashion beauty',
    ];

    public function ensureForPromo(Tenant $tenant, Promo $promo, bool $force = false): void
    {
        $keys = collect(
            app(PromoThemeIcons::class)->iconsForPromo($promo->offers ?? [], $promo->description, $tenant)
        )->pluck('key')->unique()->values()->all();

        $this->ensure($tenant, $keys, $force);
    }

    /** @param  array<int, string>  $keys */
    public function ensure(Tenant $tenant, array $keys, bool $force = false): int
    {
        $generated = 0;

        foreach ($keys as $key) {
            if (! isset($this->themes[$key])) {
                continue;
            }

            if (! $force && $this->hasCached($tenant, $key)) {
                continue;
            }

            if ($this->generate($tenant, $key)) {
                $generated++;
            }
        }

        return $generated;
    }

    public function generate(Tenant $tenant, string $key): bool
    {
        $apiKey = config('services.gemini.api_key');

        if (! $apiKey || ! isset($this->themes[$key])) {
            return false;
        }

        $theme = $this->themes[$key];
        $prompt = <<<PROMPT
Genera UN SOLO file SVG valido per un'icona di centro estetico italiano.

Tema: {$theme}

Requisiti OBBLIGATORI:
- viewBox="0 0 80 80"
- xmlns="http://www.w3.org/2000/svg"
- Usa SOLO fill="currentColor" e/o stroke="currentColor" (niente colori hex)
- Stile: silhouette morbida, linee organiche, elegante, come logo beauty salon (profilo donna, capelli fluidi)
- NO cerchi tecnici, NO icone ingegneristiche, NO testo, NO gradienti
- Massimo 6 elementi grafici (path, circle, ellipse)
- Rispondi SOLO con il tag <svg>...</svg> completo, senza markdown e senza spiegazioni
PROMPT;

        $models = array_values(array_unique(array_filter([
            config('services.gemini.model', 'gemini-2.5-flash'),
            ...config('services.gemini.fallback_models', []),
        ])));

        foreach ($models as $model) {
            try {
                $response = Http::timeout(90)->post(
                    "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}",
                    [
                        'contents' => [
                            ['parts' => [['text' => $prompt]]],
                        ],
                        'generationConfig' => [
                            'temperature' => 0.4,
                        ],
                    ]
                );

                if ($response->status() === 429) {
                    Log::warning('Gemini icon quota exceeded', ['key' => $key]);

                    return false;
                }

                if ($response->failed()) {
                    continue;
                }

                $text = data_get($response->json(), 'candidates.0.content.parts.0.text');

                if (! $text) {
                    continue;
                }

                $svg = $this->extractSvg($text);

                if ($svg && $this->save($tenant, $key, $svg)) {
                    return true;
                }
            } catch (\Throwable $e) {
                Log::warning('Gemini icon generation failed', [
                    'key' => $key,
                    'model' => $model,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return false;
    }

    public function hasCached(Tenant $tenant, string $key): bool
    {
        return is_readable($this->path($tenant, $key));
    }

    public function readCached(Tenant $tenant, string $key): ?string
    {
        $path = $this->path($tenant, $key);

        if (! is_readable($path)) {
            return null;
        }

        $svg = trim((string) file_get_contents($path));

        return str_contains($svg, '<svg') ? $svg : null;
    }

    private function path(Tenant $tenant, string $key): string
    {
        return storage_path("app/public/brand-icons/ai/{$tenant->slug}/{$key}.svg");
    }

    private function save(Tenant $tenant, string $key, string $svg): bool
    {
        $path = $this->path($tenant, $key);
        $dir = dirname($path);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return file_put_contents($path, $svg) !== false;
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

        $svg = preg_replace('/\s(fill|stroke)\s*=\s*["\']#[0-9a-fA-F]{3,8}["\']/', ' $1="currentColor"', $svg) ?? $svg;

        if (! str_contains($svg, 'viewBox')) {
            $svg = preg_replace('/<svg\b/', '<svg viewBox="0 0 80 80"', $svg, 1) ?? $svg;
        }

        if (! preg_match('/class\s*=/', $svg)) {
            $svg = preg_replace('/<svg\b/', '<svg class="promo-icon promo-icon--ai"', $svg, 1) ?? $svg;
        }

        return $svg;
    }
}
