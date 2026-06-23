<?php

namespace App\Services;

use App\Models\Promo;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;

class PromoVisualBuilder
{
    public function __construct(
        private GeminiImageGenerator $geminiImages,
        private PromoDecorImageBuilder $decorImages,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Tenant $tenant, Promo $promo, string $originalStoragePath, ?string $referenceAbsolutePath = null): array
    {
        $dir = pathinfo($originalStoragePath, PATHINFO_DIRNAME);
        $variants = [
            'original' => $originalStoragePath,
            'flyer' => $originalStoragePath,
        ];

        $reference = $referenceAbsolutePath && is_file($referenceAbsolutePath)
            ? $referenceAbsolutePath
            : storage_path('app/public/'.$originalStoragePath);

        if (is_file($reference)) {
            try {
                $heroPath = $this->geminiImages->generateHeroBanner(
                    $tenant,
                    $promo,
                    $reference,
                    $dir.'/hero-ai.jpg',
                );

                if ($heroPath) {
                    $variants['hero'] = $heroPath;
                }
            } catch (\Throwable $e) {
                Log::warning('Gemini hero image skipped', ['message' => $e->getMessage()]);
            }

            try {
                $decor = $this->decorImages->build($tenant, $promo, $reference);

                if ($decor !== []) {
                    $variants['decor'] = $decor;
                }
            } catch (\Throwable $e) {
                Log::warning('Promo decor images skipped', ['message' => $e->getMessage()]);
            }
        }

        $variants['og'] = $variants['hero'] ?? $originalStoragePath;

        return $variants;
    }
}
