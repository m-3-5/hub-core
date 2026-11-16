<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\FacebookPages;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

/** «Pubblica da Facebook»: collega la pagina una volta sola, i post con #promo diventano promo (e vanno nel sito). */
class FacebookController extends Controller
{
    public function show(Tenant $tenant): View
    {
        return view('admin.facebook', [
            'tenant' => $tenant,
            'enabled' => FacebookPages::enabled(),
            'fb' => FacebookPages::connection($tenant),
            'hashtags' => FacebookPages::hashtags($tenant),
            'pages' => session('fb_pages_'.$tenant->id) ? array_map(fn ($p) => ['id' => $p['id'], 'name' => $p['name']], Crypt::decrypt(session('fb_pages_'.$tenant->id))) : [],
        ]);
    }

    public function connect(Tenant $tenant, FacebookPages $facebook): RedirectResponse
    {
        abort_unless(FacebookPages::enabled(), 404);

        $nonce = Str::random(32);
        session(['fb_nonce' => $nonce]);
        $state = Crypt::encryptString(json_encode(['t' => $tenant->id, 'n' => $nonce]));

        return redirect()->away($facebook->loginUrl($state, route('facebook.callback')));
    }

    /** Ritorno da Facebook (un solo indirizzo per tutte le attività: l'attività è nello «state» cifrato). */
    public function callback(Request $request, FacebookPages $facebook): RedirectResponse
    {
        try {
            $state = json_decode(Crypt::decryptString((string) $request->query('state')), true);
        } catch (Throwable) {
            abort(403);
        }

        $tenant = Tenant::find($state['t'] ?? 0);
        $user = $request->user();
        abort_unless($tenant && hash_equals((string) session()->pull('fb_nonce', ''), (string) ($state['n'] ?? 'x')), 403);
        abort_unless($user->isSuperAdmin() || $user->belongsToTenant($tenant), 403);

        $back = redirect()->route('admin.facebook.show', $tenant);

        if (! $request->filled('code')) {
            return $back->withErrors(['facebook' => 'Collegamento annullato: riprova quando vuoi.']);
        }

        try {
            $pages = $facebook->pagesFromCode((string) $request->query('code'), route('facebook.callback'));
        } catch (Throwable $e) {
            report($e);

            return $back->withErrors(['facebook' => 'Facebook non ha risposto: riprova tra un minuto.']);
        }

        if (! $pages) {
            return $back->withErrors(['facebook' => 'Non abbiamo trovato pagine Facebook gestite da te. Serve una pagina (non un profilo personale).']);
        }

        if (count($pages) === 1) {
            return $this->link($tenant, $pages[0], $facebook);
        }

        // Più pagine: si sceglie quale collegare.
        session(['fb_pages_'.$tenant->id => Crypt::encrypt($pages)]);

        return $back->with('success', 'Scegli quale pagina collegare.');
    }

    public function choose(Request $request, Tenant $tenant, FacebookPages $facebook): RedirectResponse
    {
        $pages = session('fb_pages_'.$tenant->id) ? Crypt::decrypt(session('fb_pages_'.$tenant->id)) : [];
        $page = collect($pages)->firstWhere('id', (string) $request->input('page_id'));
        abort_unless($page, 404);
        session()->forget('fb_pages_'.$tenant->id);

        return $this->link($tenant, $page, $facebook);
    }

    private function link(Tenant $tenant, array $page, FacebookPages $facebook): RedirectResponse
    {
        $back = redirect()->route('admin.facebook.show', $tenant);

        try {
            $facebook->connect($tenant, $page);
        } catch (RuntimeException $e) {
            return $back->withErrors(['facebook' => $e->getMessage()]);
        } catch (Throwable $e) {
            report($e);

            return $back->withErrors(['facebook' => 'Facebook non ha accettato il collegamento: riprova tra un minuto.']);
        }

        return $back->with('success', 'Fatto! La pagina «'.$page['name'].'» è collegata. Scrivi #promo in un post con una foto e diventa una promo.');
    }

    public function hashtags(Request $request, Tenant $tenant): RedirectResponse
    {
        $fb = FacebookPages::connection($tenant);
        abort_unless($fb, 404);

        $data = $request->validate(['hashtags' => ['required', 'string', 'max:120']], ['hashtags.required' => 'Scrivi almeno un hashtag, per esempio #promo.']);
        preg_match_all('/#?([\p{L}\p{N}_]{2,30})/u', $data['hashtags'], $m);
        $tags = array_slice(array_values(array_unique(array_map(fn ($t) => '#'.mb_strtolower($t), $m[1]))), 0, 5);

        if (! $tags) {
            return back()->withErrors(['hashtags' => 'Scrivi almeno un hashtag, per esempio #promo.']);
        }

        $settings = $tenant->settings;
        $settings['facebook']['hashtags'] = $tags;
        $tenant->forceFill(['settings' => $settings])->save();

        return back()->with('success', 'Hashtag salvati: '.implode(' ', $tags));
    }

    public function disconnect(Tenant $tenant, FacebookPages $facebook): RedirectResponse
    {
        $facebook->disconnect($tenant);

        return redirect()->route('admin.facebook.show', $tenant)->with('success', 'Pagina scollegata: i nuovi post non diventano più promo.');
    }

    /** Verifica dell'indirizzo da parte di Meta. */
    public function verify(Request $request): Response
    {
        $token = (string) config('services.facebook.verify_token');

        abort_unless($token !== '' && $request->query('hub_mode') === 'subscribe' && hash_equals($token, (string) $request->query('hub_verify_token')), 403);

        return response((string) $request->query('hub_challenge'), 200, ['Content-Type' => 'text/plain']);
    }

    /** Avvisi di Meta: nuovi post delle pagine collegate (firmati con il segreto dell'app). */
    public function webhook(Request $request, FacebookPages $facebook): JsonResponse
    {
        $secret = (string) config('services.facebook.app_secret');
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);
        abort_unless($secret !== '' && hash_equals($expected, (string) $request->header('X-Hub-Signature-256')), 403);

        foreach ((array) $request->input('entry', []) as $entry) {
            $tenant = FacebookPages::tenantForPage((string) ($entry['id'] ?? ''));

            if (! $tenant) {
                continue;
            }

            foreach ((array) ($entry['changes'] ?? []) as $change) {
                $v = $change['value'] ?? [];

                if (($change['field'] ?? null) === 'feed' && ($v['verb'] ?? null) === 'add' && in_array($v['item'] ?? null, ['status', 'photo', 'post'], true) && ! empty($v['post_id'])) {
                    $postId = (string) $v['post_id'];
                    // Si risponde subito a Meta; il post si legge dopo.
                    dispatch(function () use ($facebook, $tenant, $postId) {
                        try {
                            $facebook->handlePost($tenant, $postId);
                        } catch (Throwable $e) {
                            report($e);
                        }
                    })->afterResponse();
                }
            }
        }

        return response()->json(['ok' => true]);
    }
}
