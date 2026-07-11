<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FlyerVideoGenerator
{
    private const FRAME_COUNT = 18;

    private const FRAME_DELAY_CENTISECONDS = 14;

    private const ZOOM_END = 1.14;

    /**
     * Turns a static SVG flyer into a short looping animated GIF (slow zoom-in),
     * so it can be shared as a "video" on Instagram/WhatsApp stories. Uses only
     * Imagick (already used elsewhere for PNG rasterization) — no ffmpeg needed.
     *
     * @return array{path: string, mime: string}|null
     */
    public function generateFromSvg(string $svgAbsolutePath, string $directory): ?array
    {
        if (! extension_loaded('imagick') || ! is_file($svgAbsolutePath)) {
            return null;
        }

        if (function_exists('set_time_limit')) {
            set_time_limit(90);
        }

        try {
            $base = new \Imagick();
            $base->setBackgroundColor(new \ImagickPixel('transparent'));
            $base->setResolution(216, 216);
            $base->readImage($svgAbsolutePath);
            $base->setImageFormat('png32');

            $width = $base->getImageWidth();
            $height = $base->getImageHeight();

            $gif = new \Imagick();

            for ($i = 0; $i < self::FRAME_COUNT; $i++) {
                $t = $i / (self::FRAME_COUNT - 1);
                $scale = 1 + ($t * (self::ZOOM_END - 1));

                $frame = clone $base;
                $cropW = (int) round($width / $scale);
                $cropH = (int) round($height / $scale);
                $cropX = (int) round(($width - $cropW) / 2);
                $cropY = (int) round(($height - $cropH) / 2);

                $frame->cropImage($cropW, $cropH, $cropX, $cropY);
                $frame->resizeImage($width, $height, \Imagick::FILTER_LANCZOS, 1);
                $frame->setImageFormat('gif');
                $frame->setImageDelay(self::FRAME_DELAY_CENTISECONDS);
                $frame->setImageDispose(\Imagick::DISPOSE_NONE);

                $gif->addImage($frame);
                $frame->clear();
                $frame->destroy();
            }

            $base->clear();
            $base->destroy();

            $gif->setImageIterations(0);
            $optimized = $gif->optimizeImageLayers();

            Storage::disk('public')->makeDirectory($directory);
            $path = $directory.'/'.Str::random(24).'.gif';
            Storage::disk('public')->put($path, $optimized->getImagesBlob());

            $optimized->destroy();
            $gif->destroy();

            return ['path' => $path, 'mime' => 'image/gif'];
        } catch (\Throwable) {
            return null;
        }
    }
}
