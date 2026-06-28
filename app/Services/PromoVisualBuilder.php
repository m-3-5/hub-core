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
        private PromoVisualFallback $fallback,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Tenant $tenant, Promo $promo, string $originalStoragePath, ?string $referenceAbsolutePath = null, ?bool $aiImages = null): array
    {
        $dir = pathinfo($originalStoragePath, PATHINFO_DIRNAME);
        $variants = [
            'original' => $originalStoragePath,
            'flyer' => $originalStoragePath,
        ];

        $reference = $referenceAbsolutePath && is_file($referenceAbsolutePath)
            ? $referenceAbsolutePath
            : storage_path('app/public/'.$originalStoragePath);

        $useAi = $aiImages ?? (bool) config('hub.promo_ai_images', false);

        if (is_file($reference)) {
            $topics = PromoOfferTopics::topicsForPromo($promo->offers ?? [], $promo->description);
            $decor = [];

            if ($useAi) {
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
                } catch (\Throwable $e) {
                    Log::warning('Promo decor images skipped', ['message' => $e->getMessage()]);
                }
            }

            $decor = $this->fallback->ensureDecor($tenant, $promo, $decor, $topics);

            if ($decor !== []) {
                $variants['decor'] = $decor;
            }
        }

        return $this->fallback->ensureBaseVariants($variants, $originalStoragePath);
    }
}
