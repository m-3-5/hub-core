<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GeminiSvgFlyerGenerator
{
    public function __construct(
        private GeminiModelResolver $models,
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

        $prompt = <<<PROMPT
Genera SOLO il codice SVG (nessun markdown, nessuna spiegazione, inizia direttamente con <svg) di un volantino promozionale verticale 800x1200px per l'attività "{$tenant->name}".
Colore principale del brand: {$color} — usalo come sfondo o elemento grafico dominante.
Testo principale grande e leggibile: "{$safeHeadline}"
PROMPT;

        if ($subline) {
            $prompt .= "\nTesto secondario più piccolo sotto: \"".Str::limit($subline, 100, '')."\"";
        }

        $prompt .= "\nStile moderno, pulito, elegante, adatto a un'attività commerciale italiana. Forme decorative semplici (cerchi, forme organiche), buon contrasto testo/sfondo. NO loghi disegnati, NO testo illeggibile, NO watermark.";

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
