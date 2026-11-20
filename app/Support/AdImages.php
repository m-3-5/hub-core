<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Foto degli annunci dei privati: il telefono le rimpicciolisce già prima di inviarle (vedi classifieds/new);
 * qui ci assicuriamo che non superino 1600 px e prepariamo la foto piccola (480 px) per elenchi e piramide35.com.
 * Senza GD (o con un formato che GD non legge) si salva il file così com'è.
 */
class AdImages
{
    public const MAX_SIDE = 1600;

    public const THUMB_SIDE = 480;

    public static function store(UploadedFile $file, string $dir): string
    {
        $jpeg = self::resized((string) file_get_contents($file->getRealPath()), self::MAX_SIDE, 82);

        if ($jpeg === null) {
            return $file->store($dir, 'public');
        }

        $path = $dir.'/'.Str::random(32).'.jpg';
        Storage::disk('public')->put($path, $jpeg);

        return $path;
    }

    /** Foto piccola della prima immagine; null se non si riesce (si userà la foto grande). */
    public static function thumb(string $path, string $dir): ?string
    {
        $jpeg = self::resized((string) Storage::disk('public')->get($path), self::THUMB_SIDE, 72);

        if ($jpeg === null) {
            return null;
        }

        $thumb = $dir.'/thumb-'.Str::random(24).'.jpg';
        Storage::disk('public')->put($thumb, $jpeg);

        return $thumb;
    }

    private static function resized(string $bytes, int $maxSide, int $quality): ?string
    {
        if ($bytes === '' || ! function_exists('imagecreatefromstring')) {
            return null;
        }

        try {
            $src = @imagecreatefromstring($bytes);
            if (! $src) {
                return null;
            }

            $w = imagesx($src);
            $h = imagesy($src);
            $scale = min(1, $maxSide / max($w, $h));
            $nw = max(1, (int) round($w * $scale));
            $nh = max(1, (int) round($h * $scale));

            $dst = imagecreatetruecolor($nw, $nh);
            imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255)); // PNG trasparenti: sfondo bianco
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

            ob_start();
            imagejpeg($dst, null, $quality);

            return (string) ob_get_clean() ?: null;
        } catch (Throwable) {
            return null;
        }
    }
}
