<?php

namespace App\Http\Controllers;

use App\Models\Promo;
use App\Models\Tenant;
use App\Services\GuestAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * «Ultimo passo»: chi ha iniziato da ospite si registra una volta sola, quando vuole pubblicare.
 * Email o cellulare + password (oppure Google). Dopo, la promo scelta va online (se l'IA la ritiene adatta)
 * e il sito diventa visibile.
 */
class GuestClaimController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $tenant = $this->guestTenant($request);
        if (! $tenant) {
            return redirect()->route('app.index');
        }

        $promo = $this->promoFor($tenant, $request->query('promo'));
        $request->session()->put('guest_claim', [
            'user' => $request->user()->id,
            'promo' => $promo?->id,
            'next' => $this->safeNext($request->query('torna')),
        ]);

        return view('guest.claim', [
            'tenant' => $tenant,
            'promo' => $promo,
            'googleEnabled' => GoogleLoginController::configured(),
        ]);
    }

    public function store(Request $request, GuestAccount $accounts): RedirectResponse
    {
        $tenant = $this->guestTenant($request);
        if (! $tenant) {
            return redirect()->route('app.index');
        }

        $request->validate([
            'contact' => ['required', 'string', 'max:190'],
            'password' => ['required', 'string', 'min:8', 'max:190'],
            'consent' => ['accepted'],
        ], [
            'contact.required' => 'Scrivi la tua email o il tuo cellulare.',
            'password.required' => 'Scegli una password.',
            'password.min' => 'La password deve avere almeno 8 caratteri.',
            'consent.accepted' => 'Serve il tuo consenso per continuare.',
        ]);

        $contact = GuestAccount::parseContact((string) $request->input('contact'));
        if (! $contact) {
            return back()->withInput($request->only('contact'))->withErrors(['contact' => 'Scrivi un\'email valida o un numero di cellulare.']);
        }
        if ($accounts->contactTaken($contact, $request->user())) {
            return back()->withInput($request->only('contact'))->withErrors(['contact' => ($contact[0] === 'email' ? 'Questa email' : 'Questo numero').' ha già un account: entra da «Accedi».']);
        }

        $accounts->claim($tenant, $request->user(), $contact, (string) $request->input('password'));

        return self::afterClaim($request, $tenant, $accounts);
    }

    /** Dopo la registrazione (anche da Google): pubblica la promo scelta e torna dove si era. */
    public static function afterClaim(Request $request, Tenant $tenant, GuestAccount $accounts): RedirectResponse
    {
        $claim = $request->session()->pull('guest_claim', []);
        $promo = ! empty($claim['promo']) ? $tenant->promos()->whereKey($claim['promo'])->first() : null;

        if ($promo) {
            $result = $accounts->publishPromo($tenant, $promo);
            if (! empty($result['payment'])) {
                return \App\Support\PendingPublication::redirect($tenant, 'promo', $promo->id)->with('success', $result['message']);
            }
            $redirect = redirect()->route('admin.promos.show', [$tenant, $promo]);

            return $result['published'] ? $redirect->with('success', $result['message']) : $redirect->withErrors(['moderation' => $result['message']]);
        }

        return redirect()->to($claim['next'] ?? route('app.home', $tenant))->with('success', 'Fatto! Sei registrato: ora quello che crei può andare online.');
    }

    private function guestTenant(Request $request): ?Tenant
    {
        return $request->user()?->tenants()->get()->first(fn (Tenant $t) => $t->isGuestPending());
    }

    private function promoFor(Tenant $tenant, mixed $id): ?Promo
    {
        return $id ? $tenant->promos()->whereKey((int) $id)->first() : null;
    }

    /** Solo indirizzi interni (niente rimandi verso altri siti). */
    private function safeNext(mixed $next): ?string
    {
        return is_string($next) && str_starts_with($next, '/') && ! str_starts_with($next, '//') ? $next : null;
    }
}
