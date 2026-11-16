<?php

namespace App\Services;

use App\Models\Tenant;
use App\Support\PayPerUse;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Pagine Facebook collegate: il titolare collega la pagina una volta sola (accesso ufficiale Meta),
 * ogni nuovo post con l'hashtag (#promo) diventa una promo. Con la carta salvata e il controllo dei contenuti
 * superato va online da sola (e quindi anche nel sito); altrimenti resta una bozza da approvare.
 *
 * Dati in tenant->settings['facebook']: page_id, page_name, token (cifrato), hashtags, connected_at.
 */
class FacebookPages
{
    public static function enabled(): bool
    {
        return (bool) (config('services.facebook.app_id') && config('services.facebook.app_secret'));
    }

    public static function connection(Tenant $tenant): ?array
    {
        $fb = $tenant->settings['facebook'] ?? null;

        return is_array($fb) && ! empty($fb['page_id']) ? $fb : null;
    }

    public static function hashtags(Tenant $tenant): array
    {
        $tags = self::connection($tenant)['hashtags'] ?? [config('services.facebook.hashtag', '#promo')];

        return array_values(array_filter(array_map(fn ($t) => '#'.ltrim(mb_strtolower(trim((string) $t)), '#'), (array) $tags), fn ($t) => $t !== '#'));
    }

    private function graph(string $path): string
    {
        return 'https://graph.facebook.com/'.config('services.facebook.graph_version', 'v27.0').'/'.ltrim($path, '/');
    }

    public function loginUrl(string $state, string $redirect): string
    {
        return 'https://www.facebook.com/'.config('services.facebook.graph_version', 'v27.0').'/dialog/oauth?'.http_build_query([
            'client_id' => config('services.facebook.app_id'),
            'redirect_uri' => $redirect,
            'state' => $state,
            'scope' => 'pages_show_list,pages_read_engagement,pages_manage_metadata',
            'response_type' => 'code',
        ]);
    }

    /**
     * Dal codice di ritorno alle pagine che la persona gestisce (con i token di pagina che non scadono).
     *
     * @return array<int, array{id: string, name: string, token: string}>
     */
    public function pagesFromCode(string $code, string $redirect): array
    {
        $short = Http::timeout(15)->get($this->graph('oauth/access_token'), [
            'client_id' => config('services.facebook.app_id'),
            'client_secret' => config('services.facebook.app_secret'),
            'redirect_uri' => $redirect,
            'code' => $code,
        ])->throw()->json('access_token');

        // Token lungo: così anche i token delle pagine non scadono.
        $long = Http::timeout(15)->get($this->graph('oauth/access_token'), [
            'grant_type' => 'fb_exchange_token',
            'client_id' => config('services.facebook.app_id'),
            'client_secret' => config('services.facebook.app_secret'),
            'fb_exchange_token' => $short,
        ])->throw()->json('access_token') ?: $short;

        $pages = Http::timeout(15)->withToken((string) $long)->get($this->graph('me/accounts'), ['fields' => 'id,name,access_token'])->throw()->json('data') ?? [];

        return array_values(array_map(fn ($p) => ['id' => (string) $p['id'], 'name' => (string) ($p['name'] ?? ''), 'token' => (string) $p['access_token']],
            array_filter($pages, fn ($p) => ! empty($p['id']) && ! empty($p['access_token']))));
    }

    /** Collega la pagina all'attività e la iscrive agli avvisi dei nuovi post. */
    public function connect(Tenant $tenant, array $page): void
    {
        $other = Tenant::query()->where('id', '!=', $tenant->id)->whereNotNull('settings')->get()
            ->first(fn (Tenant $t) => (self::connection($t)['page_id'] ?? null) === $page['id']);
        if ($other) {
            throw new RuntimeException('Questa pagina Facebook è già collegata a un\'altra attività.');
        }

        Http::timeout(15)->asForm()->post($this->graph($page['id'].'/subscribed_apps'), [
            'subscribed_fields' => 'feed',
            'access_token' => $page['token'],
        ])->throw();

        $settings = $tenant->settings ?? [];
        $settings['facebook'] = [
            'page_id' => $page['id'],
            'page_name' => $page['name'],
            'token' => Crypt::encryptString($page['token']),
            'hashtags' => self::hashtags($tenant),
            'connected_at' => now()->toIso8601String(),
        ];
        $tenant->forceFill(['settings' => $settings])->save();
    }

    public function disconnect(Tenant $tenant): void
    {
        $fb = self::connection($tenant);

        if ($fb) {
            try {
                Http::timeout(10)->delete($this->graph($fb['page_id'].'/subscribed_apps'), ['access_token' => Crypt::decryptString($fb['token'])]);
            } catch (Throwable $e) {
                report($e);
            }
        }

        $settings = $tenant->settings ?? [];
        unset($settings['facebook']);
        $tenant->forceFill(['settings' => $settings])->save();
    }

    public static function tenantForPage(string $pageId): ?Tenant
    {
        return Tenant::query()->whereNotNull('settings')->where('settings', 'like', '%'.$pageId.'%')->get()
            ->first(fn (Tenant $t) => (self::connection($t)['page_id'] ?? null) === $pageId);
    }

    /**
     * Un nuovo post sulla pagina: con #promo (o gli hashtag del cliente) diventa una promo; con gli altri
     * hashtag (#prodotto, #servizio, #novita, #evento, #blog, #lavori) va nella sezione giusta (FacebookSections).
     * Restituisce quello che è stato creato (o null).
     */
    public function handlePost(Tenant $tenant, string $postId): mixed
    {
        $fb = self::connection($tenant);
        if (! $fb) {
            return null;
        }

        $post = Http::timeout(15)->get($this->graph($postId), [
            'fields' => 'message,full_picture,permalink_url,created_time',
            'access_token' => Crypt::decryptString($fb['token']),
        ])->throw()->json();

        $message = trim((string) ($post['message'] ?? ''));
        $lower = mb_strtolower($message);
        $tagged = collect(self::hashtags($tenant))->contains(fn ($tag) => preg_match('/(^|\s)'.preg_quote($tag, '/').'(\b|$)/u', $lower));

        if (! $tagged) {
            $section = FacebookSections::typeOf($message);

            return $section ? app(FacebookSections::class)->handle($tenant, $section, $postId, $post) : null;
        }

        if (empty($post['full_picture'])) {
            return null; // una promo senza foto non si crea
        }

        // Titolo: la prima riga senza hashtag.
        $firstLine = trim((string) Str::of($message)->before("\n"));
        $title = trim(preg_replace('/#[\p{L}\p{N}_]+/u', '', $firstLine) ?? '') ?: Str::limit(trim(preg_replace('/#[\p{L}\p{N}_]+/u', '', $message) ?? ''), 80, '');
        $title = $title !== '' ? $title : 'Promo da Facebook';

        // Online subito solo se si può pagare e il contenuto è serio; altrimenti bozza da approvare.
        $check = app(ContentModerator::class)->check($message);
        $canPay = PayPerUse::exempt($tenant) || PayPerUse::hasCard($tenant);
        $publish = $canPay && $check['allowed'] && ! $check['unavailable'];

        $result = app(PromoDraftImporter::class)->import($tenant, [
            'source' => 'facebook',
            'source_id' => $postId,
            'source_url' => $post['permalink_url'] ?? null,
            'title' => $title,
            'description' => $message,
            'image_url' => (string) $post['full_picture'],
            'posted_at' => $post['created_time'] ?? null,
            'publish' => $publish,
        ]);

        $promo = $result['promo'];

        if ($promo && ! $result['duplicate'] && $publish && ! PayPerUse::charge($tenant, 'promo', $promo->id, $promo->title)) {
            $promo->update(['status' => 'draft', 'published_at' => null]);
        }

        Log::info('Facebook: post trasformato in promo', ['tenant' => $tenant->slug, 'post' => $postId, 'published' => $promo?->fresh()?->isPublished()]);

        return $promo;
    }
}
