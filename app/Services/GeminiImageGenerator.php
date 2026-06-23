<?php

namespace App\Services;

use App\Models\Promo;
use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiImageGenerator
{
    public function __construct(
        private GeminiModelResolver $models,
    ) {}
    public function generateHeroBanner(
        Tenant $tenant,
        Promo $promo,
        string $referenceAbsolutePath,
        string $outputRelativePath,
    ): ?string {
        $sector = $this->sectorHint($tenant);
        $offersText = $this->offersSummary($promo);

        $prompt = <<<PROMPT
Crea UN'immagine hero promozionale landscape 16:9 per sito web di {$tenant->name}.
Settore: {$sector}. Colore brand: {$tenant->primary_color}.
Tema: {$promo->title}. Offerte: {$offersText}.
Usa il materiale allegato come riferimento colori/mood — NON copiare il layout del volantino.
Niente testo illeggibile, niente watermark.
PROMPT;

        return $this->generateWithReference($referenceAbsolutePath, $prompt, $outputRelativePath);
    }

    public function generateDecorImage(
        Tenant $tenant,
        Promo $promo,
        string $topic,
        string $offerLabel,
        string $referenceAbsolutePath,
        string $outputRelativePath,
    ): ?string {
        $sector = $this->sectorHint($tenant);

        $prompt = <<<PROMPT
Crea UN'immagine quadrata 1:1 per landing promo di {$tenant->name} ({$sector}).
Argomento: {$topic} — "{$offerLabel}".
Stile coerente con il materiale allegato (colori, mood). Moderno e professionale.
Niente testo, niente watermark.
PROMPT;

        return $this->generateWithReference($referenceAbsolutePath, $prompt, $outputRelativePath);
    }

    public function generateFlyerFromBrand(
        Tenant $tenant,
        string $logoAbsolutePath,
        string $outputRelativePath,
        ?string $hint = null,
    ): ?string {
        $sector = $this->sectorHint($tenant);
        $hintText = $hint ?: "Promozione {$tenant->name}";

        $prompt = <<<PROMPT
Crea UN volantino promozionale verticale (flyer A5) per {$tenant->name}.
Settore: {$sector}. Usa il logo allegato e colore brand {$tenant->primary_color}.
Tema: {$hintText}.
Testo in italiano, leggibile, layout professionale. Niente watermark.
PROMPT;

        return $this->generateWithReference($logoAbsolutePath, $prompt, $outputRelativePath);
    }

    private function generateWithReference(
        string $referenceAbsolutePath,
        string $prompt,
        string $outputRelativePath,
    ): ?string {
        $apiKey = config('services.gemini.api_key');

        if (! $apiKey || ! is_file($referenceAbsolutePath)) {
            return null;
        }

        $this->models->ensureDiscovered();

        $mime = mime_content_type($referenceAbsolutePath) ?: 'image/jpeg';
        $imageData = base64_encode(file_get_contents($referenceAbsolutePath));

        $models = $this->models->imageModels();

        foreach ($models as $model) {
            $response = Http::timeout(180)->post(
                "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}",
                [
                    'contents' => [[
                        'parts' => [
                            ['text' => $prompt],
                            ['inline_data' => ['mime_type' => $mime, 'data' => $imageData]],
                        ],
                    ]],
                    'generationConfig' => ['responseModalities' => ['IMAGE', 'TEXT']],
                ]
            );

            if ($response->status() === 429) {
                Log::warning('Gemini image quota exceeded', ['model' => $model]);

                return null;
            }

            if ($response->failed()) {
                Log::warning('Gemini image model failed', [
                    'model' => $model,
                    'status' => $response->status(),
                    'message' => data_get($response->json(), 'error.message'),
                ]);

                continue;
            }

            if ($this->extractAndSaveImage($response->json(), $outputRelativePath)) {
                return $outputRelativePath;
            }
        }

        return null;
    }

    private function sectorHint(Tenant $tenant): string
    {
        $slug = $tenant->slug;

        return match (true) {
            str_contains($slug, 'beauty') => 'centro estetico e bellezza',
            str_contains($slug, 'piramide') || str_contains($slug, 'm-3-5') => 'tech, web agency e servizi digitali',
            default => 'servizi professionali',
        };
    }

    private function offersSummary(Promo $promo): string
    {
        return collect($promo->offers ?? [])
            ->map(fn ($o) => ($o['name'] ?? '').' '.($o['price'] ?? ''))
            ->filter()
            ->implode(', ');
    }

    private function extractAndSaveImage(?array $payload, string $relativePath): bool
    {
        $parts = data_get($payload, 'candidates.0.content.parts', []);

        foreach ($parts as $part) {
            $inline = $part['inlineData'] ?? $part['inline_data'] ?? null;

            if (! $inline || empty($inline['data'])) {
                continue;
            }

            $binary = base64_decode($inline['data']);

            if ($binary === false) {
                continue;
            }

            $full = storage_path('app/public/'.$relativePath);
            $dir = dirname($full);

            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            file_put_contents($full, $binary);

            return true;
        }

        return false;
    }
}
