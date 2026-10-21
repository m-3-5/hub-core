<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Richiede il toggle "Scheduled Tasks" attivo su Plesk (Laravel Toolkit) —
// altrimenti il cron di sistema non chiama mai "php artisan schedule:run".
Schedule::command('hub:charge-pending-module-charges')->daily();

// Finita la prima settimana gratuita: una email che chiede l'euro dell'offerta di lancio.
Schedule::command('hub:launch-offer-reminders')->dailyAt('10:00');

// Pagamenti protetti: gira ai venditori i soldi il cui compratore ha confermato o la cui attesa è finita.
Schedule::command('hub:release-protected-payments')->hourly()->withoutOverlapping();

// Siti creati con Max: avviso e poi archivio per chi non paga o non li usa. Resta spento finché HUB_SITE_LIFECYCLE=true.
Schedule::command('hub:sites-lifecycle --apply')->dailyAt('04:30')->when(fn () => (bool) config('hub.site_lifecycle.enabled'))->withoutOverlapping();

// Commissioni del mese scorso sulle vendite del canale hub → registro addebiti (con commissione 0 non scrive nulla).
Schedule::command('hub:charge-commissions')->monthlyOn(1, '03:00');

// Voci scelte di piramide35.com (pagine paese, tipi di attività) → promo pubblicate su inm35.it.
Schedule::command('piramide:sync')->dailyAt('05:15')->withoutOverlapping();
