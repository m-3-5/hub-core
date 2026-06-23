<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiModelDiscovery
{
    private const CACHE_KEY = 'gemini.model_catalog';

    private const CACHE_TTL_HOURS = 24;

    /** @var array<int, string> */
    private array $textPriority = [
        'gemini-2.5-flash-lite',
        'gemini-2.5-flash',
        'gemini-3.5-flash',
        'gemini-2.0-flash',
        'gemini-2.0-flash-lite',
        'gemini-1.5-flash',
        'gemini-1.5-flash-8b',
    ];

    /** @var array<int, string> */
    private array $imagePriority = [
        'gemini-3.1-flash-image',
        'gemini-2.5-flash-image',
        'gemini-3-pro-image',
        'gemini-3.1-flash-image-preview',
        'gemini-3-pro-image-preview',
    ];

    /** @return array{text_best: ?string, image_best: ?string, text_models: array<int, string>, image_models: array<int, string>, probes: array<string, array<string, mixed>>} */
    public function discover(bool $force = false): array
    {
        $apiKey = config('services.gemini.api_key');

        if (! $apiKey) {
            throw new \RuntimeException('GEMINI_API_KEY non configurata.');
        }

        $fingerprint = $this->keyFingerprint($apiKey);

        if (! $force) {
            $cached = Cache::get(self::CACHE_KEY);

            if (is_array($cached) && ($cached['api_key_fingerprint'] ?? '') === $fingerprint) {
                return $cached;
            }
        }

        $listed = $this->listModelNames($apiKey);
        $textCandidates = $this->mergeCandidates($this->textPriority, $listed, 'text');
        $imageCandidates = $this->mergeCandidates($this->imagePriority, $listed, 'image');

        $probes = [];
        $textWorking = [];
        $imageWorking = [];

        foreach ($textCandidates as $model) {
            $result = $this->probeText($apiKey, $model);
            $probes[$model] = $result;

            if ($result['ok']) {
                $textWorking[] = $model;
            }
        }

        foreach ($imageCandidates as $model) {
            $result = $this->probeImage($apiKey, $model);
            $probes[$model] = $result;

            if ($result['ok']) {
                $imageWorking[] = $model;
            }
        }

        $catalog = [
            'discovered_at' => now()->toIso8601String(),
            'api_key_fingerprint' => $fingerprint,
            'text_best' => $textWorking[0] ?? null,
            'image_best' => $imageWorking[0] ?? null,
            'text_models' => $textWorking,
            'image_models' => $imageWorking,
            'probes' => $probes,
        ];

        Cache::put(self::CACHE_KEY, $catalog, now()->addHours(self::CACHE_TTL_HOURS));

        return $catalog;
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** @return array<int, string> */
    private function listModelNames(string $apiKey): array
    {
        $response = Http::timeout(30)->get(
            'https://generativelanguage.googleapis.com/v1beta/models',
            ['key' => $apiKey]
        );

        if ($response->failed()) {
            Log::warning('Gemini listModels failed', ['status' => $response->status()]);

            return [];
        }

        return collect($response->json('models', []))
            ->pluck('name')
            ->map(fn (string $name) => str_replace('models/', '', $name))
            ->all();
    }

    /**
     * @param  array<int, string>  $priority
     * @param  array<int, string>  $listed
     * @return array<int, string>
     */
    private function mergeCandidates(array $priority, array $listed, string $type): array
    {
        $fromList = collect($listed)->filter(function (string $name) use ($type) {
            if ($type === 'image') {
                return str_contains($name, 'image') || str_contains($name, 'imagen');
            }

            return str_contains($name, 'flash')
                && ! str_contains($name, 'image')
                && ! str_contains($name, 'embedding')
                && ! str_contains($name, 'tts')
                && ! str_contains($name, 'audio')
                && ! str_contains($name, 'live');
        })->values()->all();

        return array_values(array_unique([...$priority, ...$fromList]));
    }

    /** @return array{ok: bool, status: int, message: ?string} */
    private function probeText(string $apiKey, string $model): array
    {
        $response = Http::timeout(45)->post(
            "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}",
            [
                'contents' => [['parts' => [['text' => 'Rispondi solo: OK']]]],
                'generationConfig' => ['maxOutputTokens' => 16],
            ]
        );

        return $this->probeResult($response, expectsImage: false);
    }

    /** @return array{ok: bool, status: int, message: ?string} */
    private function probeImage(string $apiKey, string $model): array
    {
        $response = Http::timeout(60)->post(
            "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}",
            [
                'contents' => [[
                    'parts' => [['text' => 'Generate a tiny solid green square image, no text']],
                ]],
                'generationConfig' => ['responseModalities' => ['IMAGE', 'TEXT']],
            ]
        );

        return $this->probeResult($response, expectsImage: true);
    }

    /** @return array{ok: bool, status: int, message: ?string} */
    private function probeResult(\Illuminate\Http\Client\Response $response, bool $expectsImage): array
    {
        $status = $response->status();

        if ($status === 429) {
            return ['ok' => false, 'status' => 429, 'message' => 'Quota esaurita (free tier)'];
        }

        if ($response->failed()) {
            return [
                'ok' => false,
                'status' => $status,
                'message' => data_get($response->json(), 'error.message', 'Errore'),
            ];
        }

        if ($expectsImage) {
            $parts = data_get($response->json(), 'candidates.0.content.parts', []);
            $hasImage = collect($parts)->contains(fn ($p) => isset($p['inlineData']) || isset($p['inline_data']));

            return [
                'ok' => $hasImage,
                'status' => $status,
                'message' => $hasImage ? null : 'Nessuna immagine nella risposta',
            ];
        }

        $text = data_get($response->json(), 'candidates.0.content.parts.0.text');

        return [
            'ok' => is_string($text) && $text !== '',
            'status' => $status,
            'message' => $text ? null : 'Nessun testo nella risposta',
        ];
    }

    private function keyFingerprint(string $apiKey): string
    {
        return substr(hash('sha256', $apiKey), 0, 12);
    }
}
