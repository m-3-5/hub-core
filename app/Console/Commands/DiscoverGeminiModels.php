<?php

namespace App\Console\Commands;

use App\Services\GeminiModelDiscovery;
use Illuminate\Console\Command;

class DiscoverGeminiModels extends Command
{
    protected $signature = 'gemini:discover-models {--force : Ignora cache e riprova tutti i modelli}';

    protected $description = 'Trova i migliori modelli Gemini gratuiti disponibili per testo e immagini';

    public function handle(GeminiModelDiscovery $discovery): int
    {
        $this->info('Scansione modelli Gemini (può richiedere 1-2 minuti)…');

        $catalog = $discovery->discover((bool) $this->option('force'));

        $this->newLine();
        $this->line('Chiave API: fingerprint '.($catalog['api_key_fingerprint'] ?? 'n/d'));
        $this->line('Scansione: '.($catalog['discovered_at'] ?? 'ora'));

        $this->newLine();
        $this->components->twoColumnDetail(
            '<fg=green>Testo (migliore)</>',
            $catalog['text_best'] ?? '<fg=red>nessuno disponibile</>'
        );

        if (! empty($catalog['text_models'])) {
            foreach (array_slice($catalog['text_models'], 1) as $model) {
                $this->components->twoColumnDetail('  fallback testo', $model);
            }
        }

        $this->components->twoColumnDetail(
            '<fg=green>Immagini (migliore)</>',
            $catalog['image_best'] ?? '<fg=yellow>nessuno (quota free esaurita?)</>'
        );

        if (! empty($catalog['image_models'])) {
            foreach (array_slice($catalog['image_models'], 1) as $model) {
                $this->components->twoColumnDetail('  fallback immagini', $model);
            }
        }

        if ($this->output->isVerbose()) {
            $this->newLine();
            $this->comment('Dettaglio probe:');
            foreach ($catalog['probes'] ?? [] as $model => $probe) {
                $status = ($probe['ok'] ?? false) ? 'OK' : 'FAIL';
                $msg = $probe['message'] ?? '';
                $this->line("  {$model}: {$status} (HTTP {$probe['status']}) {$msg}");
            }
        }

        $this->newLine();

        if ($catalog['text_best']) {
            $this->info('Suggerimento .env: GEMINI_MODEL='.$catalog['text_best']);
        }

        if ($catalog['image_best']) {
            $this->info('Suggerimento .env: GEMINI_IMAGE_MODEL='.$catalog['image_best']);
        } else {
            $this->warn('Immagini IA non disponibili su free tier con questa chiave.');
            $this->line('Opzioni: billing su Google AI Studio, nuova chiave, oppure carica volantino già pronto.');
        }

        $this->line('Cache valida 24h. I generatori useranno automaticamente questi modelli.');

        return self::SUCCESS;
    }
}
