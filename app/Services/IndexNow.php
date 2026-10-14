<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * IndexNow: avvisa in automatico i motori di ricerca (Bing, Yandex e altri) quando una pagina è nuova o cambiata,
 * così promo, annunci, servizi e prodotti vengono indicizzati subito e non quando passa il crawler.
 * Si attiva da solo in produzione (INDEXNOW_ENABLED per forzare). La chiave è derivata da APP_KEY, nessuna configurazione.
 */
class IndexNow
{
    public static function key(): string
    {
        // Stessa chiave del comando hub:indexnow, pubblicata su /indexnow-key.txt.
        return \App\Console\Commands\IndexNowSubmit::key();
    }

    public static function enabled(): bool
    {
        return (bool) config('services.indexnow.enabled', app()->isProduction());
    }

    public static function keyUrl(): string
    {
        return route('indexnow.key');
    }

    /** Mette in coda la segnalazione di un indirizzo, da eseguire dopo la risposta all'utente. */
    public static function queue(string $url): void
    {
        if (! self::enabled()) {
            return;
        }

        dispatch(fn () => self::ping([$url]))->afterResponse();
    }

    /**
     * @param  array<int, string>  $urls
     */
    public static function ping(array $urls): bool
    {
        $urls = array_values(array_unique(array_filter($urls)));

        if ($urls === []) {
            return false;
        }

        try {
            $response = Http::timeout(5)->acceptJson()->post('https://api.indexnow.org/indexnow', [
                'host' => parse_url(url('/'), PHP_URL_HOST),
                'key' => self::key(),
                'keyLocation' => self::keyUrl(),
                'urlList' => $urls,
            ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::info('IndexNow non raggiungibile', ['message' => $e->getMessage()]);

            return false;
        }
    }
}
