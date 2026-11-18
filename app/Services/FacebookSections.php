<?php

namespace App\Services;

use App\Models\Tenant;
use App\Support\PayPerUse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use M35\HubPayments\Models\PayableService;
use Throwable;

/**
 * Gli altri hashtag di «Pubblica da Facebook», uguali per tutti i clienti (come il sito di Beauty of Image):
 *  #prodotto → un prodotto in vetrina (1 €), #servizio → un servizio (1 €),
 *  #novita, #evento, #blog → la sezione «Novità ed eventi» del sito (gratis),
 *  #lavori → una foto in più nella galleria del sito (gratis).
 * #promo (e gli hashtag scelti dal cliente) restano in FacebookPages: diventano promo.
 */
class FacebookSections
{
    /** In ordine di priorità: un post con più hashtag va nella prima sezione trovata. */
    public const TAGS = [
        'product' => ['prodotto', 'prodotti'],
        'service' => ['servizio', 'servizi', 'nuovoservizio'],
        'event' => ['evento', 'eventi'],
        'news' => ['novita', 'novità', 'news'],
        'blog' => ['blog'],
        'work' => ['lavori', 'lavoro', 'galleria'],
    ];

    /** Novità ed eventi tenuti nel sito (i più recenti). */
    public const MAX_NEWS = 12;

    /** Foto massime nella galleria del sito. */
    public const MAX_PHOTOS = 12;

    private const MONTHS = [
        'gennaio' => 1, 'febbraio' => 2, 'marzo' => 3, 'aprile' => 4, 'maggio' => 5, 'giugno' => 6,
        'luglio' => 7, 'agosto' => 8, 'settembre' => 9, 'ottobre' => 10, 'novembre' => 11, 'dicembre' => 12,
    ];

    public function __construct(private readonly PromoDraftImporter $photos) {}

    public static function hasTag(string $message, string $tag): bool
    {
        return (bool) preg_match('/(^|[^\p{L}\p{N}_])#'.preg_quote($tag, '/').'(?![\p{L}\p{N}_])/iu', $message);
    }

    public static function typeOf(string $message): ?string
    {
        foreach (self::TAGS as $type => $tags) {
            foreach ($tags as $tag) {
                if (self::hasTag($message, $tag)) {
                    return $type;
                }
            }
        }

        return null;
    }

    /**
     * Titolo (prima riga) e testo senza gli hashtag di sezione; gli altri hashtag restano come parole.
     *
     * @return array{title: string, text: string}
     */
    public static function text(string $message): array
    {
        $all = array_merge(...array_values(self::TAGS));
        $clean = preg_replace('/(^|\s)#('.implode('|', array_map(fn ($t) => preg_quote($t, '/'), $all)).')(?![\p{L}\p{N}_])/iu', '$1', $message) ?? $message;
        $clean = preg_replace('/#([\p{L}\p{N}_]+)/u', '$1', $clean) ?? $clean;
        $clean = trim(preg_replace("/[ \t]+/", ' ', $clean) ?? $clean);

        $lines = array_values(array_filter(array_map('trim', explode("\n", $clean)), fn ($l) => $l !== ''));
        $title = Str::limit($lines[0] ?? '', 120, '');

        return ['title' => $title, 'text' => trim(implode("\n", array_slice($lines, 1)))];
    }

    /** Il prezzo scritto nel post («25 €», «€ 25,90», «12 euro»), in centesimi. */
    public static function price(string $message): ?int
    {
        if (preg_match('/€\s*(\d{1,5}(?:[.,]\d{1,2})?)/u', $message, $m) || preg_match('/(\d{1,5}(?:[.,]\d{1,2})?)\s*(?:€|euro\b)/iu', $message, $m)) {
            $cents = (int) round(((float) str_replace(',', '.', $m[1])) * 100);

            return $cents >= 50 ? $cents : null;
        }

        return null;
    }

    /** La data dell'evento scritta nel post («24 ottobre alle 18:30», «24/10 ore 21»). */
    public static function eventDate(string $message, Carbon $postedAt): ?Carbon
    {
        $months = implode('|', array_keys(self::MONTHS));

        if (preg_match('/(?<![\d\/])(\d{1,2})\s*[\/.]\s*(\d{1,2})(?:\s*[\/.]\s*(\d{2,4}))?(?![\d\/])/u', $message, $m)) {
            [$day, $month, $year] = [(int) $m[1], (int) $m[2], ($m[3] ?? '') ?: null];
        } elseif (preg_match('/(\d{1,2})\s+('.$months.')(?:\s+(\d{4}))?/iu', $message, $m)) {
            [$day, $month, $year] = [(int) $m[1], self::MONTHS[mb_strtolower($m[2])], ($m[3] ?? '') ?: null];
        } else {
            return null;
        }

        $tz = config('app.timezone');
        $explicitYear = $year !== null;
        $year = $year === null ? (int) $postedAt->copy()->timezone($tz)->year : (int) (strlen($year) === 2 ? '20'.$year : $year);

        if (! checkdate($month, $day, $year)) {
            return null;
        }

        [$hour, $minute] = preg_match('/(?:ore|alle)\s*(\d{1,2})(?:[:.](\d{2}))?/iu', $message, $t) && (int) $t[1] < 24
            ? [(int) $t[1], (int) ($t[2] ?? 0)]
            : [0, 0];

        $date = Carbon::create($year, $month, $day, $hour, $minute, 0, $tz);

        return ! $explicitYear && $date->lt($postedAt->copy()->startOfDay()) ? $date->addYear() : $date;
    }

    /**
     * Mette il post nella sezione giusta. Restituisce cosa è stato creato (o null).
     *
     * @param  array{message?: string, full_picture?: string, permalink_url?: string, created_time?: string}  $post
     */
    public function handle(Tenant $tenant, string $type, string $postId, array $post): mixed
    {
        $message = trim((string) ($post['message'] ?? ''));
        $check = app(ContentModerator::class)->check($message);

        try {
            return match ($type) {
                'product', 'service' => $this->catalog($tenant, $type, $postId, $post, $message, $check),
                'event', 'news', 'blog' => $check['allowed'] ? $this->news($tenant, $type, $postId, $post, $message) : null,
                'work' => $check['allowed'] ? $this->work($tenant, $postId, $post) : null,
                default => null,
            };
        } catch (Throwable $e) {
            Log::warning('Facebook: post non smistato', ['tenant' => $tenant->slug, 'post' => $postId, 'type' => $type, 'message' => $e->getMessage()]);

            return null;
        }
    }

    /** #prodotto / #servizio: in vetrina e nel sito se c'è il prezzo, la carta e il testo è a posto; se no resta da completare. */
    private function catalog(Tenant $tenant, string $type, string $postId, array $post, string $message, array $check): ?PayableService
    {
        $existing = PayableService::query()->where('tenant_id', $tenant->id)->get()
            ->first(fn (PayableService $s) => ($s->metadata['facebook_post_id'] ?? null) === $postId);
        if ($existing) {
            return $existing;
        }

        ['title' => $title, 'text' => $text] = self::text($message);
        $title = $title !== '' ? $title : ($type === 'product' ? 'Prodotto da Facebook' : 'Servizio da Facebook');
        $cover = ! empty($post['full_picture'])
            ? $this->photos->downloadPhoto($tenant, (string) $post['full_picture'], ($type === 'product' ? 'products' : 'services').'/'.$tenant->slug)
            : null;
        $price = self::price($message);

        $canPay = PayPerUse::canPay($tenant);
        $publish = $price !== null && $canPay && $check['allowed'] && ! $check['unavailable'];

        $item = PayableService::create([
            'tenant_id' => $tenant->id,
            'type' => $type,
            'title' => $title,
            'slug' => PayableService::uniqueSlugForTenant($tenant->id, $title),
            'description' => $text !== '' ? Str::limit($text, 2000, '') : null,
            'cover_image_path' => $cover,
            'amount_cents' => $price ?? 0,
            'currency' => config('hub-payments.currency', 'eur'),
            'status' => 'active',
            'published_to_site' => $publish,
            'metadata' => ['facebook_post_id' => $postId, 'facebook_url' => $post['permalink_url'] ?? null, 'from' => 'facebook'],
        ]);

        if ($publish && ! PayPerUse::charge($tenant, $type, $item->id, $item->title)) {
            $item->update(['published_to_site' => false]);
        }

        if ($item->fresh()->published_to_site) {
            app(WordPressWebhookDispatcher::class)->servicesSync($tenant);
        }

        Log::info('Facebook: post diventato '.($type === 'product' ? 'prodotto' : 'servizio'), ['tenant' => $tenant->slug, 'post' => $postId, 'published' => $item->fresh()->published_to_site]);

        return $item;
    }

    /** #novita, #evento, #blog: in «Novità ed eventi» del sito, con la sua foto. Gratis. */
    private function news(Tenant $tenant, string $type, string $postId, array $post, string $message): ?array
    {
        $site = $tenant->generatedSite;
        if (! $site) {
            return null; // senza sito non c'è dove metterle
        }

        $answers = $site->answers ?? [];
        $news = collect($answers['news'] ?? []);
        if ($news->contains(fn ($n) => ($n['id'] ?? null) === $postId)) {
            return $news->firstWhere('id', $postId);
        }

        ['title' => $title, 'text' => $text] = self::text($message);
        if ($title === '') {
            return null;
        }

        $postedAt = ! empty($post['created_time']) ? Carbon::parse($post['created_time']) : now();
        $item = [
            'id' => $postId,
            'type' => $type,
            'title' => $title,
            'text' => Str::limit($text, 3000, ''),
            'image' => ! empty($post['full_picture']) ? $this->photos->downloadPhoto($tenant, (string) $post['full_picture'], 'sites/'.$tenant->slug.'/news') : null,
            'url' => $post['permalink_url'] ?? null,
            'posted_at' => $postedAt->toIso8601String(),
            'event_at' => $type === 'event' ? self::eventDate($message, $postedAt)?->toIso8601String() : null,
        ];

        $kept = $news->prepend($item)->values();
        foreach ($kept->slice(self::MAX_NEWS) as $old) {
            if (! empty($old['image'])) {
                Storage::disk('public')->delete($old['image']);
            }
        }

        $answers['news'] = $kept->take(self::MAX_NEWS)->all();
        $site->forceFill(['answers' => $answers])->save();

        return $item;
    }

    /** #lavori: una foto in più nella galleria del sito, subito dopo le prime due. Gratis. */
    private function work(Tenant $tenant, string $postId, array $post): ?string
    {
        $site = $tenant->generatedSite;
        if (! $site || empty($post['full_picture'])) {
            return null;
        }

        $answers = $site->answers ?? [];
        $done = (array) ($answers['facebook_works'] ?? []);
        if (in_array($postId, $done, true)) {
            return null;
        }

        $path = $this->photos->downloadPhoto($tenant, (string) $post['full_picture'], 'sites/'.$tenant->slug.'/media');
        $photos = array_values((array) ($answers['media']['photos'] ?? []));
        array_splice($photos, min(2, count($photos)), 0, [$path]);

        foreach (array_slice($photos, self::MAX_PHOTOS) as $old) {
            Storage::disk('public')->delete($old);
        }

        $answers['media'] = array_merge((array) ($answers['media'] ?? []), ['photos' => array_slice($photos, 0, self::MAX_PHOTOS)]);
        $answers['facebook_works'] = array_slice(array_merge([$postId], $done), 0, 200);
        $site->forceFill(['answers' => $answers])->save();

        return $path;
    }
}
