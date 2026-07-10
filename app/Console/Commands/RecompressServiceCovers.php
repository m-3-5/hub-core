<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use M35\HubPayments\Models\PayableService;
use M35\HubPayments\Support\ImageOptimizer;

class RecompressServiceCovers extends Command
{
    protected $signature = 'hub:recompress-service-covers {--dry-run : Mostra solo cosa verrebbe convertito, senza toccare i file}';

    protected $description = 'Ricomprime in WebP le foto copertina dei servizi caricate prima della conversione automatica';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $services = PayableService::query()
            ->whereNotNull('cover_image_path')
            ->where('cover_image_path', 'not like', '%.webp')
            ->get();

        if ($services->isEmpty()) {
            $this->info('Nessuna foto da ricomprimere — tutto già in WebP.');

            return self::SUCCESS;
        }

        $converted = 0;
        $failed = 0;
        $savedBytes = 0;

        foreach ($services as $service) {
            $oldPath = $service->cover_image_path;
            $fullOldPath = Storage::disk('public')->path($oldPath);

            if (! file_exists($fullOldPath)) {
                $this->warn("Saltato (file non trovato su disco): {$oldPath}");

                continue;
            }

            $mime = mime_content_type($fullOldPath) ?: 'image/jpeg';
            $oldSize = filesize($fullOldPath);
            $directory = dirname($oldPath);

            $this->line("→ {$service->title} ({$oldPath}, ".number_format($oldSize / 1024, 0)." KB)");

            if ($dryRun) {
                continue;
            }

            try {
                $newPath = ImageOptimizer::toWebp($fullOldPath, $mime, $directory);
                $newSize = filesize(Storage::disk('public')->path($newPath));

                Storage::disk('public')->delete($oldPath);
                $service->update(['cover_image_path' => $newPath]);

                $savedBytes += max(0, $oldSize - $newSize);
                $converted++;

                $this->info('  OK — '.number_format($oldSize / 1024, 0).' KB → '.number_format($newSize / 1024, 0).' KB');
            } catch (\Throwable $e) {
                $failed++;
                $this->error('  Fallito: '.$e->getMessage());
            }
        }

        if ($dryRun) {
            $this->info(count($services).' foto verrebbero ricompresse (esegui senza --dry-run per applicare).');

            return self::SUCCESS;
        }

        $this->info("Convertite: {$converted}, fallite: {$failed}, risparmiati: ".number_format($savedBytes / 1024 / 1024, 1).' MB');

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
