<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class GeminiModelResolver
{
    private const CACHE_KEY = 'gemini.model_catalog';

    public function __construct(
        private GeminiModelDiscovery $discovery,
    ) {}

    /** @return array<int, string> */
    public function textModels(): array
    {
        $catalog = $this->catalog();

        if (! empty($catalog['text_models'])) {
            return $catalog['text_models'];
        }

        return array_values(array_unique(array_filter([
            config('services.gemini.model', 'gemini-2.5-flash'),
            'gemini-2.5-flash-lite',
            ...config('services.gemini.fallback_models', []),
        ])));
    }

    /** @return array<int, string> */
    public function imageModels(): array
    {
        $catalog = $this->catalog();

        if (! empty($catalog['image_models'])) {
            return $catalog['image_models'];
        }

        return array_values(array_unique(array_filter([
            config('services.gemini.image_model', 'gemini-2.5-flash-image'),
            ...config('services.gemini.image_fallback_models', []),
        ])));
    }

    public function bestTextModel(): ?string
    {
        return $this->catalog()['text_best'] ?? $this->textModels()[0] ?? null;
    }

    public function bestImageModel(): ?string
    {
        return $this->catalog()['image_best'] ?? $this->imageModels()[0] ?? null;
    }

    public function hasImageQuota(): bool
    {
        return $this->bestImageModel() !== null;
    }

    /** @return array<string, mixed> */
    public function catalog(): array
    {
        $cached = Cache::get(self::CACHE_KEY);

        return is_array($cached) ? $cached : [];
    }

    public function ensureDiscovered(): void
    {
        if (empty($this->catalog())) {
            try {
                $this->discovery->discover();
            } catch (\Throwable) {
                // fallback env models
            }
        }
    }
}
