<?php

namespace App\Services;

use App\Models\PromoSource;
use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Porta su inm35.it le pagine paese, le pagine per tipo di attività e i servizi di piramide35.com:
 * ognuno diventa una promo pubblicata del cliente "piramide35" (sempre valida) con link alla pagina vera.
 * Si legge l'elenco /feed/inm35.json del sito, ma si portano solo le voci scelte (config hub.piramide_selezione); ogni voce crea una sola promo (registro in promo_sources) e,
 * se una promo viene eliminata a mano, non viene ricreata. Le pagine tolte dal sito vengono rimesse in bozza.
 */
class PiramideCatalogSync
{
    public const SOURCE = 'piramide35';

    /** @return array{creati: int, aggiornati: int, ritirati: int, saltati: int, errori: list<string>} */
    public function sync(Tenant $tenant, ?string $feedUrl = null, bool $dryRun = false, ?array $solo = null): array
    {
        $feedUrl ??= (string) config('hub.piramide_feed');
        $solo ??= (array) config('hub.piramide_selezione', []);
        $esito = ['creati' => 0, 'aggiornati' => 0, 'ritirati' => 0, 'saltati' => 0, 'errori' => []];

        $risposta = Http::timeout(30)->acceptJson()->get($feedUrl);
        if (! $risposta->successful() || ! is_array($voci = $risposta->json('voci'))) {
            throw new RuntimeException('Non riesco a leggere l\'elenco di piramide35.com ('.$risposta->status().').');
        }

        $host = parse_url($feedUrl, PHP_URL_HOST);
        $presenti = [];

        foreach ($voci as $voce) {
            $id = (string) ($voce['id'] ?? '');
            if ($id === '' || ! in_array($id, $solo, true) || empty($voce['titolo']) || empty($voce['url'])) {
                continue;
            }
            $presenti[] = $id;

            try {
                $this->voce($tenant, $voce, $host, $dryRun, $esito);
            } catch (Throwable $e) {
                $esito['errori'][] = $id.': '.$e->getMessage();
            }
        }

        $tolte = PromoSource::query()->where('tenant_id', $tenant->id)->where('source', self::SOURCE)
            ->whereIn('external_id', $solo)->whereNotIn('external_id', $presenti)->whereNotNull('promo_id')->with('promo')->get();
        foreach ($tolte as $riga) {
            if ($riga->promo?->status === 'published') {
                $dryRun || $riga->promo->update(['status' => 'draft', 'published_at' => null]);
                $esito['ritirati']++;
            }
        }

        return $esito;
    }

    /** @param  array<string, mixed>  $voce */
    private function voce(Tenant $tenant, array $voce, ?string $host, bool $dryRun, array &$esito): void
    {
        $id = (string) $voce['id'];
        $titolo = Str::limit(trim(strip_tags($voce['titolo'])), 120, '');
        $breve = trim(strip_tags((string) ($voce['breve'] ?? '')));
        $offerte = $this->offerte($voce);
        $riga = PromoSource::query()->where('tenant_id', $tenant->id)->where('source', self::SOURCE)->where('external_id', $id)->first();

        if ($riga && ! $riga->promo) {
            $esito['saltati']++; // eliminata a mano: non si ricrea

            return;
        }

        if ($dryRun) {
            $riga ? $esito['aggiornati']++ : $esito['creati']++;

            return;
        }

        if ($riga) {
            $promo = $riga->promo;
            $meta = $promo->ai_metadata ?? [];
            $dati = [
                'title' => $titolo,
                'description' => $breve,
                'offers' => $offerte,
                'cta_url' => $voce['url'],
                'seo_title' => Str::limit($titolo, 70, ''),
                'seo_description' => Str::limit($breve, 155, '…'),
            ];
            if (! empty($voce['immagine']) && ($meta['source_image'] ?? null) !== $voce['immagine']) {
                [$path] = $this->scaricaFoto($tenant, $voce['immagine'], $host);
                $vecchia = $promo->image_path;
                $dati['image_path'] = $path;
                $dati['image_variants'] = app(PromoVisualFallback::class)->ensureBaseVariants([], $path);
                $dati['ai_metadata'] = array_merge($meta, ['source_image' => $voce['immagine']]);
                $vecchia && Storage::disk('public')->delete($vecchia);
            }
            $promo->update($dati);
            if ($promo->status !== 'published') {
                $promo->update(['status' => 'published', 'published_at' => now()]);
            }
            $esito['aggiornati']++;

            return;
        }

        [$path] = $this->scaricaFoto($tenant, (string) ($voce['immagine'] ?? ''), $host);

        $promo = $tenant->promos()->create([
            'title' => $titolo,
            'slug' => $this->slugLibero($tenant, Str::slug($titolo) ?: $id),
            'description' => $breve,
            'offers' => $offerte,
            'cta_label' => 'Vai alla pagina',
            'cta_url' => $voce['url'],
            'image_path' => $path,
            'image_variants' => app(PromoVisualFallback::class)->ensureBaseVariants([], $path),
            'seo_title' => Str::limit($titolo, 70, ''),
            'seo_description' => Str::limit($breve, 155, '…'),
            'status' => 'published',
            'always_active' => true,
            'published_at' => now(),
            'ai_metadata' => [
                'promo_source' => 'imported',
                'imported_from' => self::SOURCE,
                'source_id' => $id,
                'source_url' => $voce['url'],
                'source_image' => $voce['immagine'] ?? null,
                'tipo' => $voce['tipo'] ?? null,
                'luogo' => $voce['luogo'] ?? null,
                'imported_at' => now()->toIso8601String(),
            ],
        ]);

        PromoSource::create([
            'tenant_id' => $tenant->id,
            'source' => self::SOURCE,
            'external_id' => $id,
            'promo_id' => $promo->id,
            'permalink' => $voce['url'],
        ]);

        $esito['creati']++;
    }

    /** @param  array<string, mixed>  $voce */
    private function offerte(array $voce): array
    {
        if (empty($voce['prezzo_da'])) {
            return [];
        }

        return [[
            'name' => Str::limit(trim(strip_tags($voce['titolo'])), 80, ''),
            'price' => 'da '.number_format((int) $voce['prezzo_da'], 0, ',', '.').' € + IVA',
            'detail' => 'Preventivo gratuito',
        ]];
    }

    /** @return array{0: string} */
    private function scaricaFoto(Tenant $tenant, string $url, ?string $host): array
    {
        $parti = parse_url($url);
        if (($parti['scheme'] ?? '') !== 'https' || ($parti['host'] ?? '') !== $host) {
            throw new RuntimeException('Foto non consentita: deve stare su '.$host.'.');
        }

        $r = Http::timeout(30)->withOptions(['allow_redirects' => false])->get($url);
        $info = $r->successful() ? @getimagesizefromstring($r->body()) : false;
        $estensioni = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (! $info || ! isset($estensioni[$info['mime']]) || strlen($r->body()) > PromoDraftImporter::MAX_IMAGE_BYTES) {
            throw new RuntimeException('Foto non scaricabile o non valida.');
        }

        $path = 'promos/'.$tenant->slug.'/'.Str::uuid().'.'.$estensioni[$info['mime']];
        Storage::disk('public')->put($path, $r->body());

        return [$path];
    }

    private function slugLibero(Tenant $tenant, string $base): string
    {
        $slug = $base;
        for ($i = 2; $tenant->promos()->where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
