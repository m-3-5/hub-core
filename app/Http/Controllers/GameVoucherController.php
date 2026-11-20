<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Support\EmailLogin;
use App\Support\GameVoucher;
use App\Support\PayPerUse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** «Hai un codice buono?»: i buoni vinti ai giochi di piramide35.com diventano credito euro. Link diretto: /buono?codice=... */
class GameVoucherController extends Controller
{
    public function show(Request $request): View
    {
        $code = GameVoucher::normalize((string) $request->query('codice'));
        $user = $request->user();

        if (! $user) {
            // dopo l'accesso (link email, password o Google) si torna qui con il codice già scritto
            $back = '/buono'.($code !== '' ? '?codice='.rawurlencode($code) : '');
            EmailLogin::rememberAfterLogin($back);

            return view('voucher.show', ['code' => $code, 'check' => null, 'tenant' => null, 'back' => $back]);
        }

        return view('voucher.show', [
            'code' => $code,
            'check' => $code !== '' ? GameVoucher::check($code) : null,
            'tenant' => EmailLogin::tenantFor($user),
            'back' => null,
        ]);
    }

    public function redeem(Request $request): RedirectResponse
    {
        $data = $request->validate(['codice' => ['required', 'string', 'max:80'], 'attivita' => ['nullable', 'string', 'max:190']], [
            'codice.required' => 'Scrivi il codice del buono.',
        ]);

        $user = $request->user();
        $tenant = ! empty($data['attivita']) ? Tenant::where('slug', $data['attivita'])->first() : null;
        if (! $tenant || ! $user->belongsToTenant($tenant)) {
            $tenant = EmailLogin::tenantFor($user);
        }

        if (! $tenant) {
            return back()->withInput()->withErrors(['codice' => 'Questo account non ha un credito su cui caricare il buono.']);
        }

        $result = GameVoucher::redeem($tenant, $user, $data['codice']);

        if (! $result['ok']) {
            return back()->withInput()->withErrors(['codice' => $result['error']]);
        }

        return redirect()->route('admin.credit.show', $tenant)
            ->with('success', '🎉 + '.PayPerUse::euro($result['cents']).' sul tuo credito (buono gioco piramide35).');
    }
}
