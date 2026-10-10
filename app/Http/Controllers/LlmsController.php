<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * /llms.txt: presentazione del sito in testo semplice per le ricerche con IA (ChatGPT, Gemini, Perplexity…):
 * chi siamo, cosa offriamo, dove, quanto costa e le pagine principali. Si aggiorna da sola con i contenuti pubblici.
 */
class LlmsController extends Controller
{
    public function __invoke(SitemapController $sitemap): Response
    {
        $text = Cache::remember('llms.txt', now()->addHour(), fn () => $this->build($sitemap));

        return response($text, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    private function build(SitemapController $sitemap): string
    {
        $city = config('landing.city');
        $place = config('landing.place');
        $plans = collect(config('landing.plans'));
        $wa = preg_replace('/\D+/', '', (string) config('landing.whatsapp'));

        $lines = [
            '# M 3.5 — Hub Core e siti web a '.$city,
            '',
            '> M 3.5 S.R.L. sviluppa Hub Core, la piattaforma per creare promozioni, vendere servizi e prodotti con pagamenti online e pubblicare tutto sul proprio sito, e realizza siti web e app per attività di '.$city.' e della Sibaritide (Calabria). Sede operativa a '.$place.'.',
            '',
            '## Siti web e app a '.$city,
            '- Pagina: '.route('landing.web'),
            '- Pacchetti: '.$plans->map(fn ($p) => $p['name'].' (da '.$p['price_offer'].' € in offerta, listino '.$p['price_regular'].' €, IVA esclusa)')->implode('; ').'.',
            '- Offerta valida fino al '.\Illuminate\Support\Carbon::parse(config('landing.offer_ends'))->format('d/m/Y').'. Preventivo gratuito e senza impegno.',
            '- Zone servite: '.implode(', ', config('landing.zones')).'.',
        ];

        if ($wa) {
            $lines[] = '- Contatto WhatsApp: https://wa.me/'.$wa;
        }

        $lines = array_merge($lines, [
            '',
            '## Hub Core',
            '- Presentazione e registrazione: '.url('/'),
            '- Prezzi: '.route('pricing.show'),
            '- Promozioni attive di tutte le attività: '.route('promo.hub-archive'),
            '- Annunci immobili: '.route('classifieds.board'),
        ]);

        if ($group = config('landing.facebook_group.url')) {
            $lines = array_merge($lines, ['', '## Community', '- '.config('landing.facebook_group.name').': '.$group]);
        }

        $lines[] = '';
        $lines[] = '## Pagine pubbliche (promo, servizi, prodotti, annunci)';

        foreach (array_slice($sitemap->urls(), 5, 80) as $url) {
            $lines[] = '- '.$url['loc'];
        }

        $lines[] = '';
        $lines[] = '## Elenco completo per i motori di ricerca';
        $lines[] = '- '.route('sitemap');

        return implode("\n", $lines)."\n";
    }
}
