<?php

namespace App\Services;

use App\Models\Promo;
use App\Models\PromoSource;
use App\Models\Tenant;
use App\Notifications\PromoDraftToApproveNotification;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Trasforma un post esterno (es. Facebook con #promo) in una bozza di promo da approvare.
 * Ogni post esterno (source + source_id) produce al massimo una promo, anche se poi viene eliminata.
 */
class PromoDraftImporter
{
    public const MAX_IMAGE_BYTES = 10 * 1024 * 1024;

    /** @return array<int, string> */
    public static function allowedImageHosts(): array
    {
        return (array) config('hub.promo_import.image_hosts', ['fbcdn.net', 'cdninstagram.com', 'facebook.com']);
    }

    public static function imageUrlAllowed(string $url): bool
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ($parts['scheme'] ?? null) !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['port'])) {
            return false;
        }

        $host = strtolower($parts['host']);

        foreach (self::allowedImageHosts() as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.'.$allowed)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{source: string, source_id: string, source_url?: ?string, title: string, description?: ?string, image_url: string, ends_at?: ?string, posted_at?: ?string, publish?: bool}  $data
     * @return array{duplicate: bool, promo: ?Promo, image_missing?: bool}
     *
     * @throws RuntimeException se la foto non si può scaricare (nessun record viene creato: chi invia riprova)
     */
    public function import(Tenant $tenant, array $data): array
    {
        $source = $data['source'];
        $sourceId = $data['source_id'];

        $existing = PromoSource::query()
            ->where('tenant_id', $tenant->id)
            ->where('source', $source)
            ->where('external_id', $sourceId)
            ->first();

        if ($existing) {
            return ['duplicate' => true, 'promo' => $existing->promo];
        }

        [$path] = $this->downloadImage($tenant, $data['image_url']);

        try {
            $marker = PromoSource::create([
                'tenant_id' => $tenant->id,
                'source' => $source,
                'external_id' => $sourceId,
                'permalink' => $data['source_url'] ?? null,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Due richieste insieme per lo stesso post: vale la prima.
            Storage::disk('public')->delete($path);

            return ['duplicate' => true, 'promo' => PromoSource::query()
                ->where('tenant_id', $tenant->id)->where('source', $source)->where('external_id', $sourceId)
                ->first()?->promo];
        }

        try {
            $promo = $this->createPromo($tenant, $data, $path);
        } catch (Throwable $e) {
            $marker->delete();
            Storage::disk('public')->delete($path);

            throw $e;
        }

        $marker->update(['promo_id' => $promo->id]);

        if (! empty($data['publish'])) {
            $promo->markPublished();
            app(WordPressWebhookDispatcher::class)->promoPublished($tenant, $promo->fresh());
        } else {
            $this->notifyOwners($tenant, $promo);
        }

        return ['duplicate' => false, 'promo' => $promo->fresh()];
    }

    /** @param  array<string, mixed>  $data */
    private function createPromo(Tenant $tenant, array $data, string $imagePath): Promo
    {
        $title = Str::limit(trim(strip_tags($data['title'])), 120, '');
        $description = isset($data['description']) ? trim(strip_tags((string) $data['description'])) : null;
        $description = $description !== '' ? Str::limit($description, 5000, '') : null;

        $endsAt = ! empty($data['ends_at']) ? \Illuminate\Support\Carbon::parse($data['ends_at'])->setTimezone(config('app.timezone')) : null;

        $promo = $tenant->promos()->create([
            'title' => $title,
            'slug' => $this->uniqueSlug($tenant, Str::slug($title) ?: 'promo'),
            'description' => $description,
            'offers' => [],
            'cta_url' => $tenant->website,
            'image_path' => $imagePath,
            'image_variants' => app(PromoVisualFallback::class)->ensureBaseVariants([], $imagePath),
            'seo_title' => Str::limit($title, 70, ''),
            'seo_description' => $description ? Str::limit(trim(preg_replace('/\s+/', ' ', $description)), 155, '…') : null,
            'status' => 'draft',
            'always_active' => false,
            'starts_at' => null,
            'ends_at' => $endsAt,
            'published_at' => null,
            'ai_metadata' => [
                'promo_source' => 'imported',
                'imported_from' => $data['source'],
                'source_id' => $data['source_id'],
                'source_url' => $data['source_url'] ?? null,
                'posted_at' => $data['posted_at'] ?? null,
                'imported_at' => now()->toIso8601String(),
            ],
        ]);

        return $promo;
    }

    /**
     * Scarica subito la foto: i link di Facebook scadono dopo qualche giorno.
     *
     * @return array{0: string} percorso sul disco public
     */
    private function downloadImage(Tenant $tenant, string $url): array
    {
        if (! self::imageUrlAllowed($url)) {
            throw new RuntimeException('Indirizzo della foto non consentito (solo https da fbcdn.net, cdninstagram.com, facebook.com).');
        }

        try {
            $response = Http::timeout(20)->withOptions(['allow_redirects' => false])->get($url);
        } catch (Throwable $e) {
            Log::warning('Import promo: download foto fallito', ['tenant' => $tenant->slug, 'message' => $e->getMessage()]);

            throw new RuntimeException('Non riesco a scaricare la foto.');
        }

        if (! $response->successful()) {
            throw new RuntimeException('La foto non è più disponibile (HTTP '.$response->status().').');
        }

        $bytes = $response->body();

        if ($bytes === '' || strlen($bytes) > self::MAX_IMAGE_BYTES) {
            throw new RuntimeException('La foto è vuota o troppo grande.');
        }

        $info = @getimagesizefromstring($bytes);
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];

        if (! $info || ! isset($extensions[$info['mime']])) {
            throw new RuntimeException('Il file scaricato non è una foto valida.');
        }

        $path = 'promos/'.$tenant->slug.'/'.Str::uuid().'.'.$extensions[$info['mime']];
        Storage::disk('public')->put($path, $bytes);

        return [$path];
    }

    private function notifyOwners(Tenant $tenant, Promo $promo): void
    {
        $recipients = $tenant->users->reject(fn ($u) => ! $u->email || str_ends_with(strtolower($u->email), '@guest.hub-core.local'));

        if ($recipients->isEmpty()) {
            return;
        }

        try {
            Notification::send($recipients, new PromoDraftToApproveNotification($promo));
        } catch (Throwable $e) {
            Log::warning('Import promo: avviso ai titolari non inviato', ['tenant' => $tenant->slug, 'message' => $e->getMessage()]);
        }
    }

    private function uniqueSlug(Tenant $tenant, string $slug): string
    {
        $base = $slug;
        $i = 1;

        while ($tenant->promos()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }
}
