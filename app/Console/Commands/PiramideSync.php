<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\PiramideCatalogSync;
use Illuminate\Console\Command;
use Throwable;

class PiramideSync extends Command
{
    protected $signature = 'piramide:sync {--dry-run : mostra cosa farebbe senza scrivere} {--solo= : elenco di voci separate da virgola, al posto della selezione di config} {--feed= : indirizzo dell\'elenco (di solito quello di config)}';

    protected $description = 'Porta su inm35.it le pagine paese, i tipi di attività e i servizi di piramide35.com come promo pubblicate del cliente piramide35';

    public function handle(PiramideCatalogSync $sync): int
    {
        $tenant = Tenant::where('slug', 'piramide35')->first();
        if (! $tenant) {
            $this->error('Cliente "piramide35" non trovato.');

            return self::FAILURE;
        }

        try {
            $esito = $sync->sync($tenant, $this->option('feed') ?: null, (bool) $this->option('dry-run'), $this->option('solo') ? array_map('trim', explode(',', $this->option('solo'))) : null);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(($this->option('dry-run') ? '[prova] ' : '')."Nuove: {$esito['creati']} · aggiornate: {$esito['aggiornati']} · ritirate: {$esito['ritirati']} · eliminate a mano (non ricreate): {$esito['saltati']}");
        foreach ($esito['errori'] as $errore) {
            $this->warn($errore);
        }

        return $esito['errori'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
