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

// Commissioni del mese scorso sulle vendite del canale hub → registro addebiti (con commissione 0 non scrive nulla).
Schedule::command('hub:charge-commissions')->monthlyOn(1, '03:00');
