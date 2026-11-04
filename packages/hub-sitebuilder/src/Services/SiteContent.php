<?php

namespace M35\HubSitebuilder\Services;

use App\Models\Tenant;
use App\Support\BrandFonts;
use Illuminate\Support\Str;
use M35\HubSitebuilder\Models\GeneratedSite;

/** Testi del sito: regole di validazione, trasformazione e salvataggio condivisi da area riservata e link segreto. */
class SiteContent
{
    /** @return array<string, array<int, string>> */
    public static function rules(): array
    {
        return [
            'tagline' => ['required', 'string', 'max:200'],
            'services' => ['required', 'string', 'max:1000'],
            'cta_label' => ['nullable', 'string', 'max:60'],
            'extra' => ['nullable', 'string', 'max:1000'],
            // Testi in più scritti dall'IA (punti di forza, servizi spiegati, domande, stile), in JSON.
            'extras' => ['nullable', 'string', 'max:12000'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public static function answers(array $validated): array
    {
        $answers = [
            'tagline' => trim(strip_tags($validated['tagline'])),
            'services' => collect(explode(',', strip_tags($validated['services'])))
                ->map(fn ($s) => trim($s))
                ->filter()
                ->take(20)
                ->values()
                ->all(),
            'cta_label' => trim(strip_tags((string) ($validated['cta_label'] ?? ''))) ?: 'Contattaci',
            'extra' => trim(strip_tags((string) ($validated['extra'] ?? ''))) ?: null,
        ];

        // Solo se il modulo li manda (la creazione guidata sì, il link segreto no: lì restano quelli di prima).
        if (array_key_exists('extras', $validated)) {
            $decoded = json_decode((string) $validated['extras'], true);
            $answers['extras'] = self::extras(is_array($decoded) ? $decoded : []);
        }

        return $answers;
    }

    /**
     * Pulisce i testi in più: niente HTML, lunghezze massime, una sola emoji come icona.
     *
     * @param  array<string, mixed>  $raw
     * @return array{service_details: array<int, array{name: string, line: string, icon: string}>, strengths: array<int, array{title: string, text: string, icon: string}>, faq: array<int, array{q: string, a: string}>, closing: string, style: ?string}
     */
    public static function extras(array $raw): array
    {
        $strengths = collect(is_array($raw['strengths'] ?? null) ? $raw['strengths'] : [])
            ->filter(fn ($s) => is_array($s))
            ->map(fn ($s) => [
                'title' => SiteCopyWriter::clean($s['title'] ?? '', 40),
                'text' => SiteCopyWriter::clean($s['text'] ?? '', 140),
                'icon' => self::icon($s['icon'] ?? ''),
            ])
            ->filter(fn ($s) => $s['title'] !== '')
            ->take(3)->values()->all();

        $faq = collect(is_array($raw['faq'] ?? null) ? $raw['faq'] : [])
            ->filter(fn ($f) => is_array($f))
            ->map(fn ($f) => ['q' => SiteCopyWriter::clean($f['q'] ?? '', 120), 'a' => SiteCopyWriter::clean($f['a'] ?? '', 300)])
            ->filter(fn ($f) => $f['q'] !== '' && $f['a'] !== '')
            ->take(5)->values()->all();

        $style = is_string($raw['style'] ?? null) ? strtolower(trim($raw['style'])) : '';

        return [
            'service_details' => self::serviceDetails($raw['service_details'] ?? []),
            'strengths' => $strengths,
            'faq' => $faq,
            'closing' => SiteCopyWriter::clean($raw['closing'] ?? '', 140),
            'style' => isset(BrandFonts::PRESETS[$style]) ? $style : null,
        ];
    }

    /** @return array<int, array{name: string, line: string, icon: string}> */
    public static function serviceDetails(mixed $raw): array
    {
        $items = is_array($raw) ? $raw : explode(',', (string) $raw);

        return collect($items)
            ->map(fn ($s) => is_array($s)
                ? ['name' => $s['name'] ?? '', 'line' => $s['line'] ?? '', 'icon' => $s['icon'] ?? '']
                : ['name' => $s, 'line' => '', 'icon' => ''])
            ->map(fn ($s) => [
                // La virgola separa i servizi nel modulo: dentro un nome diventa uno spazio.
                'name' => trim(preg_replace('/\s+/', ' ', str_replace(',', ' ', SiteCopyWriter::clean($s['name'], 60))) ?? ''),
                'line' => SiteCopyWriter::clean($s['line'], 140),
                'icon' => self::icon($s['icon']),
            ])
            ->filter(fn ($s) => $s['name'] !== '')
            ->unique('name')
            ->take(8)->values()->all();
    }

    /** Una sola emoji (o simbolo): niente lettere, numeri o HTML. Altrimenti una spunta. */
    public static function icon(mixed $value): string
    {
        $value = trim((string) $value);
        $first = function_exists('grapheme_substr') ? (grapheme_substr($value, 0, 1) ?: '') : mb_substr($value, 0, 2);

        return ($first === '' || preg_match('/[\p{L}\p{N}<>&"\'\s]/u', $first)) ? '✓' : $first;
    }

    /** Dettagli (riga e icona) per ogni servizio mostrato: chi li ha cambiati a mano li vede senza riga. */
    public static function servicesFor(array $answers): array
    {
        $details = collect($answers['extras']['service_details'] ?? [])->keyBy(fn ($d) => Str::lower($d['name']));

        return collect($answers['services'] ?? [])
            ->map(fn ($name) => $details->get(Str::lower($name)) ?? ['name' => $name, 'line' => '', 'icon' => '✓'])
            ->all();
    }

    /** Salva i testi dopo il controllo: il sito è modificato adesso, quindi ogni avviso di rimozione decade. */
    public static function save(Tenant $tenant, array $answers, ?array $hero = null): GeneratedSite
    {
        $site = $tenant->generatedSite ?: new GeneratedSite(['tenant_id' => $tenant->id]);

        // Il link segreto non manda i testi in più: si tengono quelli già salvati.
        if (! array_key_exists('extras', $answers) && isset($site->answers['extras'])) {
            $answers['extras'] = $site->answers['extras'];
        }

        $site->fill([
            'answers' => $answers,
            'status' => 'published',
            'published_at' => $site->published_at ?? now(),
            'last_edited_at' => now(),
            'warned_at' => null,
            'delete_after' => null,
        ]);

        if ($hero) {
            $site->hero_svg_path = $hero['svg_path'];
            $site->hero_video_path = $hero['video_path'];
        }

        $site->save();

        return $site;
    }

    public static function heroDir(Tenant $tenant): string
    {
        return 'sites/'.$tenant->slug.'/'.Str::uuid();
    }
}
