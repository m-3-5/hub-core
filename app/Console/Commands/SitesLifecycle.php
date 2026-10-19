<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use M35\HubSitebuilder\Services\SiteLifecycle;

class SitesLifecycle extends Command
{
    protected $signature = 'hub:sites-lifecycle {--apply : Esegui davvero (senza questa opzione mostra soltanto cosa farebbe)}';

    protected $description = 'Siti creati con Max: avvisa chi non paga o non li usa, poi li archivia per l\'amministratore (di default mostra solo l\'anteprima)';

    public function handle(SiteLifecycle $lifecycle): int
    {
        $apply = (bool) $this->option('apply');
        $report = $lifecycle->run($apply);

        foreach ($report['lines'] as $line) {
            $this->line(($apply ? '' : '[anteprima] ').$line);
        }

        $this->info(sprintf('Avvisi: %d · Archiviati: %d · Recuperati: %d · In attesa: %d%s',
            $report['warned'], $report['archived'], $report['recovered'], $report['waiting'], $apply ? '' : ' (nulla è stato modificato: usa --apply)'));

        return self::SUCCESS;
    }
}
