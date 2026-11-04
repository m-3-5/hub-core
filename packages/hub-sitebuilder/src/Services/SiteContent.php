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
            // Dati facoltativi (passi «Foto», «Dove e quando», «Altro»): arrivano solo dalla creazione guidata.
            'more' => ['nullable', 'in:1'],
            'address' => ['nullable', 'string', 'max:200'],
            'hours' => ['nullable', 'string', 'max:300'],
            'facebook' => ['nullable', 'string', 'max:200', 'regex:~^(https?://)?([a-z0-9-]+\.)?(facebook|fb)\.com/\S+$~i'],
            'instagram' => ['nullable', 'string', 'max:200', 'regex:~^(@?[A-Za-z0-9._]{1,30}|(https?://)?(www\.)?instagram\.com/[A-Za-z0-9._]{1,30}/?)$~'],
            'vat' => ['nullable', 'string', 'regex:/^\s*(IT)?\s*\d{11}\s*$/i'],
            'reviews' => ['nullable', 'array', 'max:3'],
            'reviews.*.text' => ['nullable', 'string', 'max:300'],
            'reviews.*.name' => ['nullable', 'string', 'max:60'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'photos' => ['nullable', 'array', 'max:6'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'remove_photos' => ['nullable', 'boolean'],
            'remove_logo' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'facebook.regex' => 'Scrivi l\'indirizzo della pagina Facebook (es. facebook.com/latuaattivita).',
            'instagram.regex' => 'Scrivi il nome Instagram (es. @latuaattivita).',
            'vat.regex' => 'La partita IVA ha 11 cifre.',
            'photos.max' => 'Al massimo 6 foto.',
            'photos.*.image' => 'Una delle foto non è un\'immagine.',
            'photos.*.max' => 'Una delle foto è troppo pesante (max 8 MB).',
            'logo.max' => 'Il logo è troppo pesante (max 4 MB).',
        ];
    }

    /**
     * Dati facoltativi puliti: orari, social, P.IVA, frasi dei clienti.
     *
     * @param  array<string, mixed>  $validated
     * @return array{hours: ?string, facebook: ?string, instagram: ?string, vat: ?string, reviews: array<int, array{text: string, name: string}>}
     */
    public static function info(array $validated): array
    {
        $facebook = trim((string) ($validated['facebook'] ?? ''));
        $instagram = trim((string) ($validated['instagram'] ?? ''));

        if ($instagram !== '') {
            $handle = trim(preg_replace('~^(https?://)?(www\.)?instagram\.com/~i', '', ltrim($instagram, '@')) ?? '', '/');
            $instagram = $handle !== '' ? 'https://www.instagram.com/'.$handle : '';
        }

        return [
            'hours' => SiteCopyWriter::clean($validated['hours'] ?? '', 300) ?: null,
            'facebook' => $facebook !== '' ? (preg_match('~^https?://~i', $facebook) ? $facebook : 'https://'.$facebook) : null,
            'instagram' => $instagram ?: null,
            'vat' => preg_replace('/\D/', '', (string) ($validated['vat'] ?? '')) ?: null,
            'reviews' => collect($validated['reviews'] ?? [])
                ->map(fn ($r) => ['text' => SiteCopyWriter::clean($r['text'] ?? '', 300), 'name' => SiteCopyWriter::clean($r['name'] ?? '', 60)])
                ->filter(fn ($r) => $r['text'] !== '')
                ->take(3)->values()->all(),
        ];
    }

    /**
     * Salva logo e foto nuovi (le foto nuove sostituiscono quelle di prima).
     *
     * @param  array{logo?: ?string, photos?: array<int, string>}  $old
     * @return array{media: array{logo: ?string, photos: array<int, string>}, new: array<int, string>}
     */
    public static function storeMedia(Tenant $tenant, \Illuminate\Http\Request $request, array $old): array
    {
        $dir = 'sites/'.$tenant->slug.'/media';
        $new = [];
        $logo = $request->boolean('remove_logo') ? null : ($old['logo'] ?? null);
        $photos = $request->boolean('remove_photos') ? [] : ($old['photos'] ?? []);

        if ($request->hasFile('logo')) {
            $logo = $new[] = $request->file('logo')->store($dir, 'public');
        }

        if ($request->hasFile('photos')) {
            $photos = [];

            foreach (array_slice($request->file('photos'), 0, 6) as $file) {
                $photos[] = $new[] = $file->store($dir, 'public');
            }
        }

        return ['media' => ['logo' => $logo, 'photos' => array_values($photos)], 'new' => $new];
    }

    /**
     * Foto da far controllare all'IA, rimpicciolite (meno peso, meno costo).
     *
     * @param  array<int, string>  $paths  percorsi sul disco «public»
     * @return array<int, array{mime: string, data: string}>
     */
    public static function imagesForCheck(array $paths): array
    {
        $disk = \Illuminate\Support\Facades\Storage::disk('public');
        $out = [];

        foreach ($paths as $path) {
            if (! $path || ! $disk->exists($path)) {
                continue;
            }

            $bytes = $disk->get($path);
            $small = self::shrink($bytes);
            $out[] = $small ?? ['mime' => $disk->mimeType($path) ?: 'image/jpeg', 'data' => base64_encode($bytes)];
        }

        return $out;
    }

    /** @return array{mime: string, data: string}|null */
    private static function shrink(string $bytes): ?array
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $img = @imagecreatefromstring($bytes);

        if (! $img) {
            return null;
        }

        $w = imagesx($img);
        $h = imagesy($img);
        $scale = min(1, 768 / max($w, $h));
        $small = imagescale($img, max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale)));

        ob_start();
        imagejpeg($small ?: $img, null, 80);

        return ['mime' => 'image/jpeg', 'data' => base64_encode((string) ob_get_clean())];
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

        // Il link segreto non manda testi in più, foto e dati facoltativi: si tengono quelli già salvati.
        foreach (['extras', 'info', 'media'] as $key) {
            if (! array_key_exists($key, $answers) && isset($site->answers[$key])) {
                $answers[$key] = $site->answers[$key];
            }
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
