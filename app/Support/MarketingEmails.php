<?php

namespace App\Support;

use App\Models\ClassifiedAd;
use App\Models\Promo;
use App\Models\Tenant;
use App\Models\User;
use App\Services\FacebookPages;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use M35\HubPayments\Models\PayableService;
use M35\HubSitebuilder\Services\SiteAddons;
use M35\HubSitebuilder\Services\SiteTranslations;

/**
 * Email «un'idea a settimana»: solo ai clienti già nostri, sempre con il link per non riceverle più
 * (chi lo preme non riceve più niente di promozionale; le email di servizio, come pagamenti e password, continuano).
 *
 * Catalogo di tutto quello che si può fare su inm35.it: ogni settimana l'argomento meno mandato tra quelli che il
 * cliente non usa ancora, con un testo che cambia un po' a ogni giro.
 */
class MarketingEmails
{
    public static function optedOut(Tenant $tenant): bool
    {
        return ! empty($tenant->settings['marketing']['optout_at']);
    }

    public static function setOptOut(Tenant $tenant, bool $out): void
    {
        $settings = $tenant->settings ?? [];
        $settings['marketing']['optout_at'] = $out ? now()->toIso8601String() : null;
        $tenant->forceFill(['settings' => $settings])->save();
    }

    public static function unsubscribeUrl(Tenant $tenant): string
    {
        return URL::signedRoute('email.unsubscribe', $tenant);
    }

    /** Titolari con una email vera (niente account ospite né staff di Hub Core). */
    public static function recipients(Tenant $tenant): Collection
    {
        return $tenant->users()->get()->filter(fn (User $u) => $u->email
            && ! $u->isSuperAdmin()
            && ! str_ends_with(strtolower($u->email), '@guest.hub-core.local'))->values();
    }

    /**
     * Tutto il catalogo: argomento => [varianti [titolo, testo], link, vale per questo cliente?].
     *
     * @return array<string, array{0: array<int, array{0: string, 1: string}>, 1: string, 2: bool}>
     */
    public static function catalog(Tenant $tenant): array
    {
        $site = class_exists(\M35\HubSitebuilder\Models\GeneratedSite::class) ? $tenant->generatedSite : null;
        $online = $site && $site->isPublished();
        $items = class_exists(PayableService::class) ? PayableService::where('tenant_id', $tenant->id)->pluck('type')->all() : [];
        $promos = Promo::where('tenant_id', $tenant->id);
        $hasPromo = (clone $promos)->exists();
        $recentPromo = (clone $promos)->where('created_at', '>=', now()->subDays(30))->exists();
        $sitePage = route('admin.sitebuilder.show', $tenant);

        return [
            'site' => [[
                ['🌐 Il tuo sito con l\'IA a 1 €', 'Scrivi due righe su cosa fai: l\'IA prepara il sito, pronto per il telefono, con i tasti per chiamare e WhatsApp. Lo paghi dopo 30 giorni.'],
                ['🌐 Un sito tuo in 5 minuti', 'Niente programmi da imparare: rispondi a poche domande, l\'IA scrive i testi e tu premi «Metti online». 1 €, niente abbonamento.'],
                ['🌐 I clienti ti cercano su internet', 'Con il sito a 1 € ti trovano con orari, servizi, foto e il tasto per chiamarti. Lo crei dal telefono in pochi minuti.'],
            ], $sitePage, ! $online],
            'promo' => [[
                ['📣 Pubblica una promo', 'Volantino, pagina e link da mandare su WhatsApp, pronti in un minuto.'.($online ? ' Compare da sola anche nel tuo sito.' : '')],
                ['📣 Hai un\'offerta questa settimana?', 'Trasformala in una promo: l\'IA ti aiuta con il testo e il volantino, e la condividi subito sui social.'],
                ['📣 Una promo porta clienti nuovi', 'Sconto, novità o evento: pubblicalo come promo e mandalo ai tuoi clienti su WhatsApp con un tocco.'],
            ], route('admin.promos.index', $tenant), ! $recentPromo],
            'social' => [[
                ['🎬 Video e immagini pronti per i social', 'Ogni promo ha il suo video e il pack di immagini: quadrata per il feed, verticale per Storie e Reel. Scaricali e pubblicali.'],
                ['🎬 Le tue promo anche su Instagram', 'Apri una promo e crea il pack immagini: formato giusto per feed e Storie, senza programmi di grafica.'],
                ['🎬 Un video in un clic', 'Dalla pagina della promo premi «Genera video»: pronto per Instagram e WhatsApp.'],
            ], route('admin.promos.index', $tenant), $hasPromo],
            'facebook' => [[
                ['📘 Pubblica da Facebook', 'Collega la tua pagina una volta sola: ogni post con #promo e una foto diventa una promo, anche nel tuo sito.'],
                ['📘 Scrivi una volta sola', 'Pubblichi su Facebook come sempre e aggiungi #promo: al resto pensiamo noi, promo e sito si aggiornano da soli.'],
                ['📘 Facebook e il tuo sito, insieme', 'Collega la pagina Facebook: i post con #promo arrivano da soli su inm35.it.'],
            ], FacebookPages::enabled() ? route('admin.facebook.show', $tenant) : $sitePage, FacebookPages::enabled() && ! FacebookPages::connection($tenant)],
            'services' => [[
                ['💳 Fatti pagare online i tuoi servizi', 'Crea un servizio con prezzo: ricevi un link di pagamento da mandare al cliente, e compare anche nel tuo sito.'],
                ['💳 Prenota e paga, tutto insieme', 'I tuoi clienti prenotano e pagano subito dal telefono: niente contanti, niente appuntamenti saltati.'],
                ['💳 Un link per farti pagare', 'Per ogni servizio un link di pagamento sicuro: lo mandi su WhatsApp e il cliente paga con la carta.'],
            ], route('admin.services.index', $tenant), ! in_array('service', $items, true)],
            'products' => [[
                ['🛍️ Vendi i tuoi prodotti online', 'Foto, prezzo e tasto «Compra»: i tuoi prodotti in vetrina, anche nel tuo sito.'],
                ['🛍️ Il tuo negozio sempre aperto', 'Metti online i prodotti più richiesti: i clienti li comprano anche quando sei chiuso.'],
                ['🛍️ Vetrina prodotti in 2 minuti', 'Carica una foto, scrivi il prezzo e pubblica: il prodotto ha la sua pagina da condividere.'],
            ], route('admin.products.index', $tenant), ! in_array('product', $items, true)],
            'quotes' => [[
                ['🧾 Preventivi da pagare online', 'Scrivi l\'importo e mandalo: il cliente vede il preventivo e lo paga con un tocco.'],
                ['🧾 Lavori su misura? Manda un preventivo', 'Importo libero, descrizione e link di pagamento: niente bonifici da controllare.'],
                ['🧾 Acconti senza pensieri', 'Chiedi un acconto con un preventivo online: arriva il pagamento e ti avvisiamo.'],
            ], route('admin.quotes.index', $tenant), ! in_array('quote', $items, true)],
            'classifieds' => [[
                ['📢 Gli annunci sono gratis', 'Casa, auto, oggetti, lavoro: pubblicali gratis'.($online ? ' e compaiono da soli nel tuo sito.' : '.')],
                ['📢 Hai qualcosa da vendere o affittare?', 'Pubblica un annuncio gratis con foto e prezzo. Per gli oggetti c\'è anche il pagamento protetto.'],
                ['📢 Annunci con pagamento protetto', 'Chi compra paga a noi, tu ricevi i soldi quando ha confermato. Più fiducia, più vendite.'],
            ], route('admin.classifieds.index', $tenant), ! ClassifiedAd::where('tenant_id', $tenant->id)->exists()],
            'langs' => [[
                ['🌍 Il tuo sito in altre lingue', 'Inglese, tedesco, francese e spagnolo: 1 € a lingua, traduce l\'IA e si aggiorna da sola.'],
                ['🌍 Turisti e clienti stranieri', 'Con il sito in inglese ti trovano anche loro. 1 € a lingua, una volta sola.'],
                ['🌍 Parla la lingua dei tuoi clienti', 'Aggiungi tedesco, francese o spagnolo al tuo sito: l\'IA traduce tutto in pochi secondi.'],
            ], $sitePage, $online && ! SiteTranslations::active($site)],
            'seo' => [[
                ['⚡ Fatti trovare su Google e dall\'IA', 'SEO e parole chiave, scheda per ChatGPT e Gemini, segnalazione ai motori di ricerca: 1 € a opzione.'],
                ['⚡ Su Google con le parole giuste', 'L\'IA sceglie le ricerche che fa la gente (es. «parrucchiere vicino a me») e prepara il tuo sito. 1 €.'],
                ['⚡ ChatGPT conosce la tua attività?', 'Con «Fatti trovare dall\'IA» gli assistenti leggono chi sei, dove sei e i tuoi orari. 1 €, una volta sola.'],
            ], $sitePage, $online && ! (SiteAddons::active($site, 'seo') && SiteAddons::active($site, 'ai') && SiteAddons::active($site, 'index'))],
            'messages' => [[
                ['💬 I messaggi dei clienti in un posto solo', 'Le richieste che arrivano dalle tue pagine finiscono in «Messaggi»: le leggi e rispondi dal telefono.'],
                ['💬 Non perdere una richiesta', 'Ogni contatto dalle tue promo e dal sito arriva in «Messaggi», con email di avviso.'],
                ['💬 Rispondi più in fretta', 'Apri «Messaggi» dall\'app: vedi chi ti ha scritto e da quale pagina.'],
            ], route('admin.customer-tickets.index', $tenant), true],
            'app' => [[
                ['📱 Hub Core come un\'app sul telefono', 'Apri inm35.it dal telefono e premi «Installa l\'app»: la trovi tra le tue app, sempre a portata di mano.'],
                ['📱 Tutto dal telefono', 'Promo, sito, messaggi e pagamenti: installa Hub Core sul telefono e gestisci tutto in un tocco.'],
                ['📱 Un\'icona in più, mille minuti in meno', 'Installa Hub Core: niente password da cercare, entri e pubblichi subito.'],
            ], route('app.home', $tenant), true],
        ];
    }

    /**
     * Le idee di questa settimana: la principale (argomento meno mandato tra quelli utili) e una seconda «Lo sapevi?».
     *
     * @return array<int, array{0: string, 1: string, 2: string, 3: string}> [titolo, testo, link, argomento]
     */
    public static function tips(Tenant $tenant): array
    {
        $sent = (array) ($tenant->settings['marketing']['sent'] ?? []);
        $order = array_flip(array_keys(self::catalog($tenant)));

        return collect(self::catalog($tenant))
            ->filter(fn ($t) => $t[2])
            ->map(function ($t, $key) use ($sent) {
                $n = (int) ($sent[$key] ?? 0);
                [$title, $text] = $t[0][$n % count($t[0])]; // un giro dopo l'altro, testo un po' diverso

                return ['title' => $title, 'text' => $text, 'url' => $t[1], 'key' => $key, 'n' => $n];
            })
            ->sortBy(fn ($t) => [$t['n'], $order[$t['key']]])
            ->take(2)
            ->map(fn ($t) => [$t['title'], $t['text'], $t['url'], $t['key']])
            ->values()
            ->all();
    }

    /** Ricorda cosa è stato mandato, così la volta dopo si passa all'argomento successivo. */
    public static function remember(Tenant $tenant, array $tips): void
    {
        $settings = $tenant->settings ?? [];
        foreach ($tips as $tip) {
            $key = $tip[3] ?? null;
            if ($key) {
                $settings['marketing']['sent'][$key] = (int) ($settings['marketing']['sent'][$key] ?? 0) + 1;
            }
        }
        $settings['marketing']['last_tips_at'] = now()->toIso8601String();
        $tenant->forceFill(['settings' => $settings])->save();
    }
}
