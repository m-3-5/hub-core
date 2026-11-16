<?php

namespace M35\HubSitebuilder\Services;

use App\Models\Tenant;
use App\Services\GeminiTextClient;
use App\Support\PayPerUse;
use M35\HubSitebuilder\Models\GeneratedSite;
use Throwable;

/**
 * Il sito in altre lingue: 1 € per lingua, una volta sola. L'IA traduce i testi del cliente,
 * le scritte fisse della pagina sono qui sotto. Quando il sito cambia, le lingue attive si ritraducono da sole (gratis).
 *
 * Tutto vive in answers['i18n']: ['active' => ['en'], 'wanted' => ['de'], 'texts' => ['en' => [...]], 'hash' => ['en' => '...']].
 */
class SiteTranslations
{
    /** codice => [nome nella lingua, bandiera, nome in italiano, locale] */
    public const LANGS = [
        'en' => ['English', '🇬🇧', 'inglese', 'en_GB'],
        'de' => ['Deutsch', '🇩🇪', 'tedesco', 'de_DE'],
        'fr' => ['Français', '🇫🇷', 'francese', 'fr_FR'],
        'es' => ['Español', '🇪🇸', 'spagnolo', 'es_ES'],
    ];

    public function __construct(private readonly GeminiTextClient $gemini) {}

    /** @return array<int, string> lingue già online per questo sito */
    public static function active(?GeneratedSite $site): array
    {
        $i18n = $site?->answers['i18n'] ?? [];

        return array_values(array_filter((array) ($i18n['active'] ?? []), fn ($l) => isset(self::LANGS[$l], $i18n['texts'][$l])));
    }

    /** @return array<int, string> lingue scelte che aspettano la carta */
    public static function wanted(?GeneratedSite $site): array
    {
        return array_values(array_filter((array) ($site?->answers['i18n']['wanted'] ?? []), fn ($l) => isset(self::LANGS[$l])));
    }

    public static function names(array $langs): string
    {
        return implode(', ', array_map(fn ($l) => self::LANGS[$l][2] ?? $l, $langs));
    }

    /** I testi del cliente da tradurre (stessa forma che si rimette nel sito). */
    public static function source(array $answers): array
    {
        $extras = $answers['extras'] ?? [];

        return [
            'tagline' => (string) ($answers['tagline'] ?? ''),
            'cta_label' => (string) ($answers['cta_label'] ?? ''),
            'about' => (string) ($answers['extra'] ?? ''),
            'services' => array_map(fn ($s) => ['name' => $s['name'], 'line' => (string) ($s['line'] ?? '')], SiteContent::servicesFor($answers)),
            'strengths' => array_map(fn ($s) => ['title' => (string) ($s['title'] ?? ''), 'text' => (string) ($s['text'] ?? '')], $extras['strengths'] ?? []),
            'faq' => array_map(fn ($f) => ['q' => (string) ($f['q'] ?? ''), 'a' => (string) ($f['a'] ?? '')], $extras['faq'] ?? []),
            'closing' => (string) ($extras['closing'] ?? ''),
            'hours' => (string) ($answers['info']['hours'] ?? ''),
        ];
    }

    public static function hash(array $answers): string
    {
        return md5(json_encode(self::source($answers), JSON_UNESCAPED_UNICODE));
    }

    /** Le risposte del sito con i testi nella lingua chiesta (l'italiano resta com'è). */
    public static function localized(array $answers, string $lang): array
    {
        $t = $answers['i18n']['texts'][$lang] ?? null;

        if ($lang === 'it' || ! is_array($t)) {
            return $answers;
        }

        $services = SiteContent::servicesFor($answers);
        $details = [];
        foreach ($services as $i => $s) {
            $details[] = ['name' => $t['services'][$i]['name'] ?? $s['name'], 'line' => $t['services'][$i]['line'] ?? ($s['line'] ?? ''), 'icon' => $s['icon']];
        }

        $answers['tagline'] = $t['tagline'] ?: ($answers['tagline'] ?? '');
        $answers['cta_label'] = $t['cta_label'] ?: ($answers['cta_label'] ?? '');
        $answers['extra'] = $t['about'] ?: ($answers['extra'] ?? null);
        $answers['services'] = array_column($details, 'name');
        $answers['extras']['service_details'] = $details;

        foreach (['strengths' => ['title', 'text'], 'faq' => ['q', 'a']] as $key => $fields) {
            foreach ($answers['extras'][$key] ?? [] as $i => $row) {
                foreach ($fields as $f) {
                    if (! empty($t[$key][$i][$f])) {
                        $answers['extras'][$key][$i][$f] = $t[$key][$i][$f];
                    }
                }
            }
        }

        if (! empty($t['closing'])) {
            $answers['extras']['closing'] = $t['closing'];
        }
        if (! empty($t['hours'])) {
            $answers['info']['hours'] = $t['hours'];
        }

        return $answers;
    }

    /** Traduce i testi con l'IA. null = l'IA non ha risposto bene (non si paga niente). */
    public function translateTexts(array $answers, string $lang): ?array
    {
        if (! isset(self::LANGS[$lang]) || ! $this->gemini->isConfigured()) {
            return null;
        }

        $source = self::source($answers);
        $json = json_encode($source, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $target = self::LANGS[$lang][2];
        $prompt = <<<PROMPT
Traduci in {$target} i testi di questo sito di una piccola attività italiana. Rispondi SOLO con un JSON con le stesse chiavi e lo stesso numero di elementi negli elenchi.
Regole: traduzione naturale e semplice, come la scriverebbe un madrelingua; non aggiungere né togliere informazioni; lascia invariati nomi propri, nomi dell'attività, marchi (es. Invisalign), numeri, orari e città; testi vuoti restano vuoti; niente emoji nuove.

{$json}
PROMPT;

        try {
            $raw = $this->gemini->generate($prompt, json: true, temperature: 0.2, timeout: 45, maxModels: 3);
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        $raw = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($raw)) ?? $raw;
        $data = json_decode($raw, true);

        if (! is_array($data) || count($data['services'] ?? []) !== count($source['services'])) {
            return null;
        }

        $c = fn ($v, $max) => SiteCopyWriter::clean($v, $max);
        $rows = fn ($key, array $fields, int $max) => array_map(
            fn ($i) => array_combine($fields, array_map(fn ($f) => $c($data[$key][$i][$f] ?? '', $max), $fields)),
            array_keys($source[$key]),
        );

        $out = [
            'tagline' => $c($data['tagline'] ?? '', 200),
            'cta_label' => $c($data['cta_label'] ?? '', 60),
            'about' => $c($data['about'] ?? '', 1200),
            'services' => $rows('services', ['name', 'line'], 160),
            'strengths' => $rows('strengths', ['title', 'text'], 200),
            'faq' => $rows('faq', ['q', 'a'], 300),
            'closing' => $c($data['closing'] ?? '', 200),
            'hours' => $c($data['hours'] ?? '', 300),
        ];

        return $out['tagline'] !== '' ? $out : null;
    }

    /**
     * Mette online le lingue scelte: prima traduce, poi registra 1 € a lingua (si paga solo ciò che è riuscito).
     *
     * @return array{done: array<int, string>, failed: array<int, string>}
     */
    public function activate(Tenant $tenant, GeneratedSite $site, array $langs): array
    {
        $answers = $site->answers ?? [];
        $i18n = $answers['i18n'] ?? [];
        $done = [];
        $failed = [];

        foreach (array_values(array_unique($langs)) as $lang) {
            if (! isset(self::LANGS[$lang]) || in_array($lang, self::active($site), true)) {
                continue;
            }

            $texts = $this->translateTexts($answers, $lang);

            if (! $texts || ! PayPerUse::charge($tenant, 'lang_'.$lang, $site->id, 'Sito di '.$tenant->name.' in '.self::LANGS[$lang][2])) {
                $failed[] = $lang;

                continue;
            }

            $i18n['texts'][$lang] = $texts;
            $i18n['hash'][$lang] = self::hash($answers);
            $i18n['active'] = array_values(array_unique([...($i18n['active'] ?? []), $lang]));
            $done[] = $lang;
        }

        $i18n['wanted'] = array_values(array_diff($i18n['wanted'] ?? [], $done));
        $answers['i18n'] = $i18n;
        $site->forceFill(['answers' => $answers])->save();

        return ['done' => $done, 'failed' => $failed];
    }

    /** Dopo una modifica del sito: ritraduce (gratis) le lingue attive se i testi sono cambiati. */
    public function refresh(GeneratedSite $site): void
    {
        $answers = $site->answers ?? [];
        $active = self::active($site);

        if (! $active) {
            return;
        }

        $hash = self::hash($answers);
        $changed = false;

        foreach ($active as $lang) {
            if (($answers['i18n']['hash'][$lang] ?? null) === $hash) {
                continue;
            }

            // Se l'IA non risponde resta la traduzione di prima: meglio un testo un po' vecchio che niente.
            if ($texts = $this->translateTexts($answers, $lang)) {
                $answers['i18n']['texts'][$lang] = $texts;
                $answers['i18n']['hash'][$lang] = $hash;
                $changed = true;
            }
        }

        if ($changed) {
            $site->forceFill(['answers' => $answers])->save();
        }
    }

    /** Le scritte fisse della pagina nella lingua del visitatore. */
    public static function ui(string $lang, string $it): string
    {
        if ($lang === 'it') {
            return $it;
        }

        return self::UI[$it][$lang] ?? $it;
    }

    public static function dayNames(string $lang): array
    {
        return [
            'it' => ['', 'Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab', 'Dom'],
            'en' => ['', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            'de' => ['', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'],
            'fr' => ['', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'],
            'es' => ['', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'],
        ][$lang] ?? self::dayNames('it');
    }

    /** Scritte per «Aperto ora / Chiuso» (nel browser). */
    public static function openNowLabels(string $lang): array
    {
        $full = [
            'it' => ['lunedì', 'martedì', 'mercoledì', 'giovedì', 'venerdì', 'sabato', 'domenica'],
            'en' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
            'de' => ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'],
            'fr' => ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'],
            'es' => ['lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'],
        ][$lang] ?? [];

        return [
            'days' => ['', ...$full],
            'open' => self::ui($lang, 'Aperto ora · chiude alle'),
            'closed' => self::ui($lang, 'Chiuso · apre'),
            'at' => self::ui($lang, 'alle'),
            'tomorrow' => self::ui($lang, 'domani alle'),
        ];
    }

    private const UI = [
        'Servizi' => ['en' => 'Services', 'de' => 'Leistungen', 'fr' => 'Services', 'es' => 'Servicios'],
        'Chi siamo' => ['en' => 'About us', 'de' => 'Über uns', 'fr' => 'Qui sommes-nous', 'es' => 'Quiénes somos'],
        'Offerte' => ['en' => 'Offers', 'de' => 'Angebote', 'fr' => 'Offres', 'es' => 'Ofertas'],
        'Acquista' => ['en' => 'Shop', 'de' => 'Kaufen', 'fr' => 'Acheter', 'es' => 'Comprar'],
        'Foto' => ['en' => 'Photos', 'de' => 'Fotos', 'fr' => 'Photos', 'es' => 'Fotos'],
        'Domande' => ['en' => 'FAQ', 'de' => 'Fragen', 'fr' => 'Questions', 'es' => 'Preguntas'],
        'Contatti' => ['en' => 'Contact', 'de' => 'Kontakt', 'fr' => 'Contact', 'es' => 'Contacto'],
        'Sezioni' => ['en' => 'Sections', 'de' => 'Bereiche', 'fr' => 'Sections', 'es' => 'Secciones'],
        'Cosa offriamo' => ['en' => 'What we offer', 'de' => 'Was wir anbieten', 'fr' => 'Ce que nous proposons', 'es' => 'Qué ofrecemos'],
        'Perché sceglierci' => ['en' => 'Why choose us', 'de' => 'Warum wir', 'fr' => 'Pourquoi nous choisir', 'es' => 'Por qué elegirnos'],
        'Offerte del momento' => ['en' => 'Current offers', 'de' => 'Aktuelle Angebote', 'fr' => 'Offres du moment', 'es' => 'Ofertas del momento'],
        'Approfittane adesso' => ['en' => 'Grab them now', 'de' => 'Jetzt zugreifen', 'fr' => 'Profitez-en maintenant', 'es' => 'Aprovéchalas ahora'],
        'Fino al' => ['en' => 'Until', 'de' => 'Bis', 'fr' => 'Jusqu\'au', 'es' => 'Hasta el'],
        'Scopri' => ['en' => 'Discover', 'de' => 'Entdecken', 'fr' => 'Découvrir', 'es' => 'Descubrir'],
        'Tutte le offerte' => ['en' => 'All offers', 'de' => 'Alle Angebote', 'fr' => 'Toutes les offres', 'es' => 'Todas las ofertas'],
        'Acquista online' => ['en' => 'Buy online', 'de' => 'Online kaufen', 'fr' => 'Acheter en ligne', 'es' => 'Compra online'],
        'Paghi subito, in sicurezza' => ['en' => 'Pay now, safely', 'de' => 'Sofort und sicher bezahlen', 'fr' => 'Payez tout de suite, en sécurité', 'es' => 'Paga ahora, con seguridad'],
        'In promo' => ['en' => 'On offer', 'de' => 'Im Angebot', 'fr' => 'En promo', 'es' => 'En oferta'],
        'Compra' => ['en' => 'Buy', 'de' => 'Kaufen', 'fr' => 'Acheter', 'es' => 'Comprar'],
        'Prenota e paga' => ['en' => 'Book and pay', 'de' => 'Buchen und bezahlen', 'fr' => 'Réserver et payer', 'es' => 'Reserva y paga'],
        'Vedi tutto' => ['en' => 'See all', 'de' => 'Alle ansehen', 'fr' => 'Tout voir', 'es' => 'Ver todo'],
        'Dai un\'occhiata' => ['en' => 'Take a look', 'de' => 'Schauen Sie rein', 'fr' => 'Jetez un œil', 'es' => 'Echa un vistazo'],
        'Apri foto' => ['en' => 'Open photo', 'de' => 'Foto öffnen', 'fr' => 'Ouvrir la photo', 'es' => 'Abrir foto'],
        'foto' => ['en' => 'photo', 'de' => 'Foto', 'fr' => 'photo', 'es' => 'foto'],
        'Cosa dicono i clienti' => ['en' => 'What customers say', 'de' => 'Was Kunden sagen', 'fr' => 'Ce que disent les clients', 'es' => 'Lo que dicen los clientes'],
        'Dicono di noi' => ['en' => 'Reviews', 'de' => 'Bewertungen', 'fr' => 'Avis', 'es' => 'Opiniones'],
        'Parola ai clienti' => ['en' => 'In our customers\' words', 'de' => 'Unsere Kunden sagen', 'fr' => 'La parole aux clients', 'es' => 'Hablan los clientes'],
        'Domande frequenti' => ['en' => 'Frequently asked questions', 'de' => 'Häufige Fragen', 'fr' => 'Questions fréquentes', 'es' => 'Preguntas frecuentes'],
        'Le risposte che cerchi' => ['en' => 'The answers you need', 'de' => 'Die Antworten, die Sie suchen', 'fr' => 'Les réponses que vous cherchez', 'es' => 'Las respuestas que buscas'],
        'Scrivici o chiamaci: ti rispondiamo presto' => ['en' => 'Message or call us: we\'ll get back to you soon', 'de' => 'Schreiben oder rufen Sie uns an: wir antworten schnell', 'fr' => 'Écrivez-nous ou appelez-nous : nous répondons vite', 'es' => 'Escríbenos o llámanos: te respondemos pronto'],
        'Scrivici su WhatsApp' => ['en' => 'Message us on WhatsApp', 'de' => 'Schreiben Sie uns auf WhatsApp', 'fr' => 'Écrivez-nous sur WhatsApp', 'es' => 'Escríbenos por WhatsApp'],
        'Chiama' => ['en' => 'Call', 'de' => 'Anrufen', 'fr' => 'Appeler', 'es' => 'Llamar'],
        'Dove e quando' => ['en' => 'Where and when', 'de' => 'Wo und wann', 'fr' => 'Où et quand', 'es' => 'Dónde y cuándo'],
        'Mostra la mappa' => ['en' => 'Show the map', 'de' => 'Karte anzeigen', 'fr' => 'Afficher la carte', 'es' => 'Mostrar el mapa'],
        'La mappa è di Google: si carica solo se la apri.' => ['en' => 'The map is from Google: it only loads if you open it.', 'de' => 'Die Karte ist von Google: sie wird nur geladen, wenn Sie sie öffnen.', 'fr' => 'La carte est de Google : elle ne se charge que si vous l\'ouvrez.', 'es' => 'El mapa es de Google: solo se carga si lo abres.'],
        'Mappa' => ['en' => 'Map', 'de' => 'Karte', 'fr' => 'Carte', 'es' => 'Mapa'],
        'Telefono' => ['en' => 'Phone', 'de' => 'Telefon', 'fr' => 'Téléphone', 'es' => 'Teléfono'],
        'Scrivici ora' => ['en' => 'Message us now', 'de' => 'Jetzt schreiben', 'fr' => 'Écrivez-nous', 'es' => 'Escríbenos ahora'],
        'Dove siamo' => ['en' => 'Find us', 'de' => 'So finden Sie uns', 'fr' => 'Où nous trouver', 'es' => 'Dónde estamos'],
        'Condividi questo sito' => ['en' => 'Share this site', 'de' => 'Diese Seite teilen', 'fr' => 'Partager ce site', 'es' => 'Compartir este sitio'],
        'Pagina' => ['en' => 'Page', 'de' => 'Seite', 'fr' => 'Page', 'es' => 'Página'],
        'Inizio' => ['en' => 'Home', 'de' => 'Start', 'fr' => 'Accueil', 'es' => 'Inicio'],
        'P.IVA' => ['en' => 'VAT', 'de' => 'USt-IdNr.', 'fr' => 'TVA', 'es' => 'IVA'],
        'Sito creato con l\'IA di' => ['en' => 'Site made with the AI of', 'de' => 'Website erstellt mit der KI von', 'fr' => 'Site créé avec l\'IA de', 'es' => 'Sitio creado con la IA de'],
        'crea il tuo' => ['en' => 'make yours', 'de' => 'erstellen Sie Ihre', 'fr' => 'créez le vôtre', 'es' => 'crea el tuyo'],
        'Chiudi' => ['en' => 'Close', 'de' => 'Schließen', 'fr' => 'Fermer', 'es' => 'Cerrar'],
        'Lingua' => ['en' => 'Language', 'de' => 'Sprache', 'fr' => 'Langue', 'es' => 'Idioma'],
        'Ciao :name, vi scrivo dal vostro sito' => ['en' => 'Hello :name, I\'m writing from your website', 'de' => 'Hallo :name, ich schreibe Ihnen über Ihre Website', 'fr' => 'Bonjour :name, je vous écris depuis votre site', 'es' => 'Hola :name, os escribo desde vuestra web'],
        'Aperto ora · chiude alle' => ['en' => 'Open now · closes at', 'de' => 'Jetzt geöffnet · schließt um', 'fr' => 'Ouvert · ferme à', 'es' => 'Abierto ahora · cierra a las'],
        'Chiuso · apre' => ['en' => 'Closed · opens', 'de' => 'Geschlossen · öffnet', 'fr' => 'Fermé · ouvre', 'es' => 'Cerrado · abre'],
        'alle' => ['en' => 'at', 'de' => 'um', 'fr' => 'à', 'es' => 'a las'],
        'domani alle' => ['en' => 'tomorrow at', 'de' => 'morgen um', 'fr' => 'demain à', 'es' => 'mañana a las'],
        'e' => ['en' => 'and', 'de' => 'und', 'fr' => 'et', 'es' => 'y'],
    ];
}
