<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FlyerImagePackGenerator
{
    /** @var array<string, array{w: int, h: int, label: string}> */
    private const FORMATS = [
        'square' => ['w' => 1080, 'h' => 1080, 'label' => 'Instagram feed (quadrata)'],
        'story' => ['w' => 1080, 'h' => 1920, 'label' => 'Storie / Reel (verticale)'],
        'original' => ['w' => 800, 'h' => 1200, 'label' => 'Originale (volantino)'],
    ];

    /**
     * Derives a set of ready-to-post static images (feed, story, original crop)
     * from the SVG flyer, for platforms that want a still image instead of a video.
     *
     * @return array<string, array{path: string, label: string}>|null
     */
    public function generateFromSvg(string $svgAbsolutePath, string $directory): ?array
    {
        if (! extension_loaded('imagick') || ! is_file($svgAbsolutePath)) {
            return null;
        }

        if (function_exists('set_time_limit')) {
            set_time_limit(60);
        }

        try {
            $base = new \Imagick();
            $base->setBackgroundColor(new \ImagickPixel('transparent'));
            $base->setResolution(216, 216);
            $base->readImage($svgAbsolutePath);
            $base->setImageFormat('png32');

            $srcW = $base->getImageWidth();
            $srcH = $base->getImageHeight();

            Storage::disk('public')->makeDirectory($directory);
            $result = [];

            foreach (self::FORMATS as $key => $format) {
                $targetRatio = $format['w'] / $format['h'];
                $srcRatio = $srcW / $srcH;

                if ($srcRatio > $targetRatio) {
                    $cropH = $srcH;
                    $cropW = (int) round($srcH * $targetRatio);
                } else {
                    $cropW = $srcW;
                    $cropH = (int) round($srcW / $targetRatio);
                }

                $cropX = (int) round(($srcW - $cropW) / 2);
                $cropY = (int) round(($srcH - $cropH) / 2);

                $frame = clone $base;
                $frame->cropImage($cropW, $cropH, $cropX, $cropY);
                $frame->resizeImage($format['w'], $format['h'], \Imagick::FILTER_LANCZOS, 1);
                $frame->setImageFormat('png32');

                $path = $directory.'/'.$key.'-'.Str::random(12).'.png';
                Storage::disk('public')->put($path, $frame->getImageBlob());
                $frame->clear();
                $frame->destroy();

                $result[$key] = ['path' => $path, 'label' => $format['label']];
            }

            $base->clear();
            $base->destroy();

            return $result;
        } catch (\Throwable) {
            return null;
        }
    }
}
