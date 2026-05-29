<?php

namespace App\Services;

use App\Models\Promo;
use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiImageGenerator
{
    public function generateHeroBanner(
        Tenant $tenant,
        Promo $promo,
        string $referenceAbsolutePath,
        string $outputRelativePath,
    ): ?string {
        $apiKey = config('services.gemini.api_key');

        if (! $apiKey) {
            return null;
        }

        $mime = mime_content_type($referenceAbsolutePath) ?: 'image/jpeg';
        $imageData = base64_encode(file_get_contents($referenceAbsolutePath));

        $offersText = collect($promo->offers ?? [])
            ->map(fn ($o) => ($o['name'] ?? '').' '.($o['price'] ?? ''))
            ->filter()
            ->implode(', ');

        $prompt = <<<PROMPT
Crea UN'immagine promozionale web per un centro estetico italiano.
Stile: elegante, moderno, rosa beauty (#e91e8c), pulito, luminoso, senza testo illeggibile.
Tema: {$promo->title}. Offerte: {$offersText}.
NON copiare il layout del volantino allegato: inventa una composizione nuova adatta a hero website (16:9).
Niente watermark, niente collage confuso. Fotografia/illustrazione raffinata per beauty salon.
PROMPT;

        $models = array_values(array_unique(array_filter([
            config('services.gemini.image_model', 'gemini-2.5-flash-image'),
            'gemini-2.0-flash-preview-image-generation',
        ])));

        foreach ($models as $model) {
            $response = Http::timeout(180)->post(
                "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}",
                [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt],
                                [
                                    'inline_data' => [
                                        'mime_type' => $mime,
                                        'data' => $imageData,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'responseModalities' => ['IMAGE', 'TEXT'],
                    ],
                ]
            );

            if ($response->failed()) {
                continue;
            }

            $saved = $this->extractAndSaveImage($response->json(), $outputRelativePath);

            if ($saved) {
                return $outputRelativePath;
            }
        }

        return null;
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
