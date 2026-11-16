<?php

namespace M35\HubSitebuilder\Services;

use App\Models\Tenant;
use App\Services\GeminiTextClient;
use App\Services\IndexNow;
use App\Support\PayPerUse;
use Illuminate\Support\Facades\Cache;
use M35\HubSitebuilder\Models\GeneratedSite;
use Throwable;

/**
 * Opzioni del sito a 1 € l'una, una volta sola, tutte automatiche:
 * - seo: l'IA sceglie le parole che la gente cerca e scrive titolo, descrizione e testi delle foto per Google;
 * - ai: scheda per ChatGPT, Gemini e simili (llms.txt) + dati strutturati con orari e domande;
 * - index: il sito entra nella mappa di inm35.it e viene segnalato subito ai motori che lo accettano.
 * Salvate in answers['addons'][nome]. Quando il sito cambia, la SEO si riscrive da sola (gratis).
 */
class SiteAddons
{
    /** nome => [icona, titolo, spiegazione breve] */
    public const ADDONS = [
        'seo' => ['🔎', 'SEO e parole chiave', 'L\'IA sceglie le parole che la gente cerca e scrive titolo e descrizione per Google.'],
        'ai' => ['🤖', 'Fatti trovare dall\'IA', 'Una scheda chiara per ChatGPT, Gemini e gli altri assistenti: chi sei, dove, orari e domande.'],
        'index' => ['📡', 'Indicizza il sito', 'Il sito entra nella mappa di inm35.it e viene segnalato subito ai motori di ricerca.'],
    ];

    public function __construct(private readonly GeminiTextClient $gemini) {}

    public static function active(?GeneratedSite $site, string $name): bool
    {
        return ! empty($site?->answers['addons'][$name]['at']);
    }

    public static function data(?GeneratedSite $site, string $name): array
    {
        return (array) ($site?->answers['addons'][$name] ?? []);
    }

    /** Attiva l'opzione e registra 1 € (solo se è riuscita). */
    public function activate(Tenant $tenant, GeneratedSite $site, string $name): bool
    {
        if (! isset(self::ADDONS[$name]) || self::active($site, $name)) {
            return isset(self::ADDONS[$name]);
        }

        $answers = $site->answers ?? [];
        $data = $this->build($tenant, $answers, $name);

        if ($data === null || ! PayPerUse::charge($tenant, 'addon_'.$name, $site->id, self::ADDONS[$name][1].' per il sito di '.$tenant->name)) {
            return false;
        }

        $answers['addons'][$name] = $data + ['at' => now()->toIso8601String()];
        $site->forceFill(['answers' => $answers])->save();
        $this->announce($tenant, $site->fresh());

        return true;
    }

    /** Dopo una modifica del sito: SEO riscritta (gratis) se i testi sono cambiati, e nuova segnalazione ai motori. */
    public function refresh(Tenant $tenant, GeneratedSite $site): void
    {
        $answers = $site->answers ?? [];

        if (self::active($site, 'seo') && ($answers['addons']['seo']['hash'] ?? null) !== self::hash($answers)) {
            if ($seo = $this->seo($tenant, $answers)) {
                $answers['addons']['seo'] = $seo + ['at' => $answers['addons']['seo']['at']];
                $site->forceFill(['answers' => $answers])->save();
            }
        }

        $this->announce($tenant, $site);
    }

    private function announce(Tenant $tenant, GeneratedSite $site): void
    {
        if (! self::active($site, 'index')) {
            return;
        }

        Cache::forget('sitemap.xml');
        IndexNow::queue(route('site.public.show', $tenant));
        foreach (SiteTranslations::active($site) as $lang) {
            IndexNow::queue(route('site.public.lang', [$tenant, $lang]));
        }
    }

    private function build(Tenant $tenant, array $answers, string $name): ?array
    {
        return match ($name) {
            'seo' => $this->seo($tenant, $answers),
            'ai', 'index' => [],
            default => null,
        };
    }

    private static function hash(array $answers): string
    {
        return md5(json_encode([SiteTranslations::source($answers), $answers['media']['photos'] ?? []], JSON_UNESCAPED_UNICODE));
    }

    /** @return array{title: string, description: string, keywords: array<int, string>, alts: array<int, string>, hash: string}|null */
    private function seo(Tenant $tenant, array $answers): ?array
    {
        if (! $this->gemini->isConfigured()) {
            return null;
        }

        $source = json_encode(SiteTranslations::source($answers), JSON_UNESCAPED_UNICODE);
        $where = $tenant->address ?: 'non indicata';
        $photos = count($answers['media']['photos'] ?? []);
        $prompt = <<<PROMPT
Sei un esperto SEO locale italiano. Questo è il sito di «{$tenant->name}» (indirizzo: {$where}). Testi del sito: {$source}
Rispondi SOLO con un JSON:
- "title": titolo per Google, max 60 caratteri, con il nome dell'attività, cosa fa e la città;
- "description": descrizione per Google, max 155 caratteri, concreta e invitante;
- "keywords": da 5 a 8 ricerche che le persone fanno davvero su Google per trovare questa attività (es. "dentista Senise", "impianti dentali Noci"), in minuscolo;
- "alts": {$photos} brevi descrizioni delle foto del sito, in ordine, max 100 caratteri l'una, con attività e città (se non sai cosa c'è nella foto, descrivi l'attività).
Regole: italiano; niente promesse («il migliore», «garantito»), niente informazioni inventate.
PROMPT;

        try {
            $raw = $this->gemini->generate($prompt, json: true, temperature: 0.3, timeout: 30, maxModels: 3);
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        $data = json_decode(preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($raw)) ?? $raw, true);
        $title = SiteCopyWriter::clean($data['title'] ?? '', 70);
        $description = SiteCopyWriter::clean($data['description'] ?? '', 170);

        if (! is_array($data) || $title === '' || $description === '') {
            return null;
        }

        return [
            'title' => $title,
            'description' => $description,
            'keywords' => array_values(array_filter(array_map(fn ($k) => mb_strtolower(SiteCopyWriter::clean($k, 60)), array_slice((array) ($data['keywords'] ?? []), 0, 8)))),
            'alts' => array_map(fn ($a) => SiteCopyWriter::clean($a, 120), array_slice((array) ($data['alts'] ?? []), 0, 6)),
            'hash' => self::hash($answers),
        ];
    }

    /** La scheda in testo semplice che leggono gli assistenti con l'IA (llms.txt). */
    public static function llmsText(Tenant $tenant, GeneratedSite $site): string
    {
        $a = $site->answers ?? [];
        $lines = ['# '.$tenant->name, '', '> '.($a['tagline'] ?? '')];

        if (! empty($a['extra'])) {
            $lines[] = '';
            $lines[] = $a['extra'];
        }

        $facts = array_filter([
            'Sito' => route('site.public.show', $tenant),
            'Telefono' => $tenant->phone,
            'Indirizzo' => $tenant->address,
            'Orari' => trim(implode(' · ', array_filter([SiteContent::scheduleText($a['info']['schedule'] ?? null), $a['info']['hours'] ?? null]))) ?: null,
            'Partita IVA' => $a['info']['vat'] ?? null,
        ]);
        $lines[] = '';
        $lines[] = '## Informazioni';
        foreach ($facts as $k => $v) {
            $lines[] = '- '.$k.': '.$v;
        }

        $services = SiteContent::servicesFor($a);
        if ($services) {
            $lines[] = '';
            $lines[] = '## Servizi';
            foreach ($services as $s) {
                $lines[] = '- '.$s['name'].($s['line'] ? ': '.$s['line'] : '');
            }
        }

        $faq = $a['extras']['faq'] ?? [];
        if ($faq) {
            $lines[] = '';
            $lines[] = '## Domande frequenti';
            foreach ($faq as $f) {
                $lines[] = '- '.$f['q'].' '.$f['a'];
            }
        }

        $langs = SiteTranslations::active($site);
        if ($langs) {
            $lines[] = '';
            $lines[] = '## Altre lingue';
            foreach ($langs as $l) {
                $lines[] = '- '.SiteTranslations::LANGS[$l][0].': '.route('site.public.lang', [$tenant, $l]);
            }
        }

        return implode("\n", $lines)."\n";
    }

    /** Dati strutturati in più (orari e domande) per Google e per gli assistenti con l'IA. */
    public static function structuredData(Tenant $tenant, GeneratedSite $site, array $answers): array
    {
        $schedule = $answers['info']['schedule'] ?? null;
        $days = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
        $hours = [];

        foreach ($schedule['ranges'] ?? [] as $r) {
            $hours[] = ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => array_map(fn ($d) => $days[$d], $schedule['days'] ?? []), 'opens' => $r[0], 'closes' => $r[1]];
        }

        $faq = array_map(fn ($f) => ['@type' => 'Question', 'name' => $f['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']]], $answers['extras']['faq'] ?? []);

        return array_filter([
            'openingHoursSpecification' => $hours ?: null,
            'knowsAbout' => array_column(SiteContent::servicesFor($answers), 'name') ?: null,
            'faq' => $faq ? ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $faq] : null,
        ]);
    }
}
