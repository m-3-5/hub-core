<?php

namespace App\Services;

use App\Exceptions\GeminiApiException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiPromoGenerator
{
    public function __construct(
        private GeminiModelResolver $models,
    ) {}

    public function generateFromImage(string $imagePath, string $mimeType, ?string $hint = null): array
    {
        $apiKey = config('services.gemini.api_key');

        if (! $apiKey) {
            throw new RuntimeException('GEMINI_API_KEY non configurata nel file .env');
        }

        $this->models->ensureDiscovered();
        $imageData = base64_encode(file_get_contents($imagePath));

        $prompt = <<<'PROMPT'
Analizza questa immagine promozionale (volantino/banner) e rispondi SOLO con un JSON valido, senza markdown, con questa struttura esatta:
{
  "title": "titolo principale della promo",
  "description": "descrizione breve 2-3 frasi in italiano",
  "offers": [
    {"name": "nome offerta", "price": "prezzo se presente", "detail": "dettaglio breve"}
  ],
  "cta_label": "testo bottone call to action in italiano",
  "seo_title": "titolo SEO max 60 caratteri",
  "seo_description": "meta description SEO max 155 caratteri in italiano",
  "suggested_slug": "slug-url-breve-in-inglese-minuscolo"
}
Estrai tutti i testi visibili (prezzi, servizi, indirizzo, telefono). Usa italiano per i contenuti visibili all'utente.
PROMPT;

        if ($hint) {
            $prompt .= "\n\nContesto fornito dal cliente su cosa vuole promuovere: \"{$hint}\". Tienine conto nel titolo e nella descrizione.";
        }

        $models = $this->models->textModels();
        $lastError = null;
        $quotaHits = 0;

        foreach ($models as $model) {
            $response = $this->callGemini($apiKey, $model, $prompt, $mimeType, $imageData);

            if ($response->status() === 429) {
                $quotaHits++;
                $lastError = data_get($response->json(), 'error.message', 'Quota esaurita per '.$model);

                continue;
            }

            if ($response->status() === 404) {
                $lastError = data_get($response->json(), 'error.message', "Modello {$model} non trovato.");

                continue;
            }

            if ($response->failed()) {
                $message = data_get($response->json(), 'error.message', 'Errore API Gemini.');

                throw new GeminiApiException('Gemini: '.$message, $response->status());
            }

            $text = data_get($response->json(), 'candidates.0.content.parts.0.text');

            if (! $text) {
                throw new RuntimeException('Gemini non ha restituito contenuto valido.');
            }

            $decoded = json_decode(trim($text), true);

            if (! is_array($decoded)) {
                throw new RuntimeException('Risposta Gemini non è JSON valido.');
            }

            $decoded['_gemini_model'] = $model;

            return $decoded;
        }

        if ($quotaHits > 0 && $quotaHits === count($models)) {
            throw new GeminiApiException(
                'Quota Gemini esaurita su tutti i modelli testo. Esegui: php artisan gemini:discover-models --force',
                429,
                true,
            );
        }

        throw new GeminiApiException(
            'Gemini: '.($lastError ?? 'Nessun modello disponibile. Esegui: php artisan gemini:discover-models --force'),
            404,
        );
    }

    public static function fallbackData(string $tenantName): array
    {
        return [
            'title' => 'Promozioni '.$tenantName,
            'description' => 'Scopri le nostre offerte speciali. Visita il centro o contattaci per informazioni.',
            'offers' => [],
            'cta_label' => 'Scopri l\'offerta',
            'seo_title' => 'Promozioni '.$tenantName,
            'seo_description' => 'Offerte e promozioni da '.$tenantName.'.',
            'suggested_slug' => 'promozioni',
            'generated_without_ai' => true,
        ];
    }

    private function callGemini(
        string $apiKey,
        string $model,
        string $prompt,
        string $mimeType,
        string $imageData,
    ): Response {
        return Http::timeout(120)
            ->post(
                "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}",
                [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt],
                                [
                                    'inline_data' => [
                                        'mime_type' => $mimeType,
                                        'data' => $imageData,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.3,
                        'responseMimeType' => 'application/json',
                    ],
                ]
            );
    }
}
