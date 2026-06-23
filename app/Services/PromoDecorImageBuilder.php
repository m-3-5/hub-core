<?php

namespace App\Services;

use App\Models\Promo;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;

class PromoDecorImageBuilder
{
    private const MAX_DECOR = 4;

    public function __construct(
        private GeminiImageGenerator $images,
    ) {}

    /**
     * @return array<string, array{path: string, label: string, key: string}>
     */
    public function build(Tenant $tenant, Promo $promo, string $referenceAbsolutePath): array
    {
        if (! is_file($referenceAbsolutePath)) {
            return [];
        }

        $dir = pathinfo($promo->image_path, PATHINFO_DIRNAME);
        $topics = PromoOfferTopics::topicsForPromo($promo->offers ?? [], $promo->description);
        $decor = [];
        $count = 0;

        foreach ($topics as $slot => $meta) {
            if ($count >= self::MAX_DECOR) {
                break;
            }

            $output = "{$dir}/decor-{$slot}.jpg";

            try {
                $saved = $this->images->generateDecorImage(
                    $tenant,
                    $promo,
                    $meta['topic'],
                    $meta['label'],
                    $referenceAbsolutePath,
                    $output,
                );

                if ($saved) {
                    $decor[$slot] = [
                        'path' => $output,
                        'label' => $meta['label'],
                        'key' => $meta['key'],
                    ];
                    $count++;
                }
            } catch (\Throwable $e) {
                Log::warning('Promo decor image skipped', [
                    'slot' => $slot,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $decor;
    }
}
