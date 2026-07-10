<?php

namespace M35\HubPayments\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ImageOptimizer
{
    /**
     * Re-encodes an image on disk as a resized WebP, stored under $directory
     * on the 'public' disk. Returns the new relative path.
     */
    public static function toWebp(string $sourcePath, string $mimeType, string $directory, int $maxWidth = 1200, int $quality = 82): string
    {
        $source = match ($mimeType) {
            'image/png' => imagecreatefrompng($sourcePath),
            'image/gif' => imagecreatefromgif($sourcePath),
            'image/webp' => imagecreatefromwebp($sourcePath),
            default => imagecreatefromjpeg($sourcePath),
        };

        if ($source === false) {
            throw new RuntimeException('Formato immagine non leggibile.');
        }

        imagepalettetotruecolor($source);
        imagealphablending($source, true);
        imagesavealpha($source, true);

        $width = imagesx($source);
        $height = imagesy($source);

        if ($width > $maxWidth) {
            $newWidth = $maxWidth;
            $newHeight = (int) round($height * ($maxWidth / $width));
            $resized = imagecreatetruecolor($newWidth, $newHeight);
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($source);
            $source = $resized;
        }

        Storage::disk('public')->makeDirectory($directory);
        $path = $directory.'/'.Str::random(32).'.webp';

        if (! imagewebp($source, Storage::disk('public')->path($path), $quality)) {
            imagedestroy($source);
            throw new RuntimeException('Conversione WebP fallita.');
        }

        imagedestroy($source);

        return $path;
    }
}
