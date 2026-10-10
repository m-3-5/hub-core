<?php

namespace App\Services;

use App\Exceptions\GeminiApiException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Chiamata di testo (con foto facoltativa) a Gemini, con ripiego sui modelli alternativi se uno è esaurito. */
class GeminiTextClient
{
    public function __construct(private readonly GeminiModelResolver $models) {}

    public function isConfigured(): bool
    {
        return (bool) config('services.gemini.api_key');
    }

    /**
     * @param  array{mime: string, data: string}|null  $image  foto già in base64
     * @return string testo della risposta (JSON puro se $json è vero)
     */
    public function generate(string $prompt, ?string $system = null, ?array $image = null, bool $json = false, float $temperature = 0.3, int $timeout = 60, ?int $maxModels = null): string
    {
        $apiKey = config('services.gemini.api_key');

        if (! $apiKey) {
            throw new RuntimeException('GEMINI_API_KEY non configurata nel file .env');
        }

        $this->models->ensureDiscovered();

        $parts = [['text' => $prompt]];

        if ($image) {
            $parts[] = ['inline_data' => ['mime_type' => $image['mime'], 'data' => $image['data']]];
        }

        $body = [
            'contents' => [['parts' => $parts]],
            'generationConfig' => array_filter([
                'temperature' => $temperature,
                'responseMimeType' => $json ? 'application/json' : null,
            ], fn ($v) => $v !== null),
        ];

        if ($system) {
            $body['systemInstruction'] = ['parts' => [['text' => $system]]];
        }

        $lastError = null;

        foreach (array_slice($this->models->textModels(), 0, $maxModels) as $model) {
            $response = Http::timeout($timeout)->post(
                rtrim((string) config('services.gemini.base_url'), '/')."/v1beta/models/{$model}:generateContent?key={$apiKey}",
                $body,
            );

            if (in_array($response->status(), [404, 429], true)) {
                $lastError = data_get($response->json(), 'error.message', "Modello {$model} non disponibile.");

                continue;
            }

            if ($response->failed()) {
                throw new GeminiApiException('Gemini: '.data_get($response->json(), 'error.message', 'Errore API Gemini.'), $response->status());
            }

            $text = data_get($response->json(), 'candidates.0.content.parts.0.text');

            if (! is_string($text) || trim($text) === '') {
                // Risposta bloccata o vuota: provo il modello successivo.
                $lastError = 'Risposta vuota.';

                continue;
            }

            return trim($text);
        }

        throw new GeminiApiException('Gemini: '.($lastError ?? 'nessun modello disponibile.'), 503);
    }
}
