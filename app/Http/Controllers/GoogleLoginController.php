<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Admin\AuthController;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/**
 * «Continua con Google» (OAuth 2 / OpenID Connect), come su app.beautyofimage.com.
 *  - chi ha già un account con quell'email entra;
 *  - chi è nuovo risponde a due domande (tipo e nome dell'attività) e l'account nasce con l'email già confermata.
 * Il tasto compare solo se nel .env ci sono GOOGLE_CLIENT_ID e GOOGLE_CLIENT_SECRET.
 */
class GoogleLoginController extends Controller
{
    public static function configured(): bool
    {
        return filled(config('services.google_login.client_id')) && filled(config('services.google_login.client_secret'));
    }

    public function redirect(Request $request): RedirectResponse
    {
        abort_unless(self::configured(), 404);

        $request->session()->put('google_oauth_state', $state = Str::random(40));
        // Da dove arriva (es. «Crea il tuo sito» da piramide35.com): serve se deve ancora registrarsi.
        $request->session()->put('google_oauth_intent', [
            'per' => $request->query('per') === 'sito' ? 'sito' : null,
            'ref' => RegistrationController::cleanSource($request->query('ref')),
        ]);

        return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => config('services.google_login.client_id'),
            'redirect_uri' => route('google.callback'),
            'response_type' => 'code',
            // Niente "profile": Google rimanderebbe "userinfo.profile" nell'indirizzo di ritorno e il firewall
            // di Plesk (regola sui file ".profile") risponde 403 prima di arrivare a Laravel.
            'scope' => 'openid email',
            'state' => $state,
            'prompt' => 'select_account',
        ]));
    }

    public function callback(Request $request): RedirectResponse
    {
        abort_unless(self::configured(), 404);

        $state = $request->session()->pull('google_oauth_state');
        $intent = $request->session()->pull('google_oauth_intent', []);

        // Lo "state" lega la risposta di Google alla richiesta partita da questo browser.
        if (! $state || ! hash_equals($state, (string) $request->query('state')) || ! $request->filled('code')) {
            return $this->failed('Accesso con Google annullato o non valido. Riprova.');
        }

        try {
            $token = Http::timeout(15)->asForm()->post('https://oauth2.googleapis.com/token', [
                'code' => $request->query('code'),
                'client_id' => config('services.google_login.client_id'),
                'client_secret' => config('services.google_login.client_secret'),
                'redirect_uri' => route('google.callback'),
                'grant_type' => 'authorization_code',
            ])->throw()->json('access_token');

            $profile = Http::timeout(15)->withToken((string) $token)->get('https://openidconnect.googleapis.com/v1/userinfo')->throw()->json();
        } catch (Throwable $e) {
            Log::warning('Login Google fallito: '.$e->getMessage());

            return $this->failed('Non è stato possibile verificare l\'account Google. Riprova tra poco.');
        }

        $email = Str::lower((string) ($profile['email'] ?? ''));

        if ($email === '' || ! filter_var($profile['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return $this->failed('Google non ha confermato un indirizzo email verificato.');
        }

        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

        if ($user) {
            Auth::login($user, remember: true);
            $request->session()->regenerate();

            return app(AuthController::class)->redirectAfterLogin($user);
        }

        // Nuovo: l'email è confermata da Google, mancano solo tipo e nome dell'attività.
        $request->session()->put('google_signup', ['email' => $email, ...$intent]);

        return redirect()->route('google.signup');
    }

    public function signup(Request $request): View|RedirectResponse
    {
        $signup = $request->session()->get('google_signup');

        if (! $signup) {
            return redirect()->route('registration.create');
        }

        return view('registration.google', ['email' => $signup['email'], 'forSite' => ($signup['per'] ?? null) === 'sito']);
    }

    public function store(Request $request): RedirectResponse
    {
        $signup = $request->session()->get('google_signup');

        if (! $signup) {
            return redirect()->route('registration.create');
        }

        $forSite = ($signup['per'] ?? null) === 'sito';

        $validated = $request->validate([
            'type' => ['required', $forSite ? 'in:azienda,ente' : 'in:azienda,privato,ente'],
            'company_name' => ['required', 'string', 'min:2', 'max:120'],
            'contact_name' => ['nullable', 'string', 'max:120'],
        ], [
            'type.required' => 'Scegli per chi è.',
            'company_name.required' => 'Scrivi il nome.',
        ]);

        // L'email potrebbe essere stata registrata nel frattempo: in quel caso si entra e basta.
        $user = User::whereRaw('LOWER(email) = ?', [$signup['email']])->first();

        if (! $user) {
            [, $user] = RegistrationController::createAccount(
                type: $validated['type'],
                companyName: trim($validated['company_name']),
                contactName: trim((string) ($validated['contact_name'] ?? '')) ?: trim($validated['company_name']),
                email: $signup['email'],
                firstModule: $forSite ? 'website' : null,
                source: $signup['ref'] ?? null,
            );
        }

        $request->session()->forget('google_signup');
        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return app(AuthController::class)->redirectAfterLogin($user)->with('success', 'Benvenuto! Il tuo spazio è pronto.');
    }

    private function failed(string $message): RedirectResponse
    {
        return redirect()->route('admin.login')->withErrors(['email' => $message]);
    }
}
