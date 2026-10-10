<?php

namespace App\Console\Commands;

use App\Http\Controllers\SitemapController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Avvisa Bing e gli altri motori che supportano IndexNow (Yandex, Seznam, Naver…) di tutte le pagine pubbliche.
 * La chiave è derivata da APP_KEY e pubblicata su /indexnow-key.txt: non serve configurare nulla.
 */
class IndexNowSubmit extends Command
{
    protected $signature = 'hub:indexnow {--dry-run : Mostra le pagine senza inviarle}';

    protected $description = 'Invia a IndexNow (Bing e altri) l\'elenco delle pagine pubbliche del sito';

    public static function key(): string
    {
        return substr(hash('sha256', 'indexnow|'.config('app.key')), 0, 32);
    }

    public function handle(SitemapController $sitemap): int
    {
        $urls = array_values(array_unique(array_column($sitemap->urls(), 'loc')));
        $this->info(count($urls).' pagine.');

        if ($this->option('dry-run')) {
            foreach ($urls as $url) {
                $this->line($url);
            }

            return self::SUCCESS;
        }

        $response = Http::timeout(20)->acceptJson()->post('https://api.indexnow.org/indexnow', [
            'host' => parse_url(url('/'), PHP_URL_HOST),
            'key' => self::key(),
            'keyLocation' => route('indexnow.key'),
            'urlList' => $urls,
        ]);

        // 200 e 202 = accettato (202: la chiave sarà verificata a breve).
        if (! in_array($response->status(), [200, 202], true)) {
            $this->error('IndexNow ha risposto '.$response->status().': '.$response->body());

            return self::FAILURE;
        }

        $this->info('Inviato a IndexNow (risposta '.$response->status().').');

        return self::SUCCESS;
    }
}
