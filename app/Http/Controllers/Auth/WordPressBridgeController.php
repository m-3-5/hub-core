<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WordPressBridgeController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $secret = config('hub.bridge_secret');

        if (! $secret) {
            abort(503, 'Bridge non configurato.');
        }

        $validated = $request->validate([
            'tenant' => ['required', 'string'],
            'wp_user' => ['required', 'string', 'max:100'],
            'ts' => ['required', 'integer'],
            'sig' => ['required', 'string'],
        ]);

        $tenant = Tenant::where('slug', $validated['tenant'])->firstOrFail();

        if (abs(time() - (int) $validated['ts']) > 300) {
            abort(403, 'Link scaduto.');
        }

        $payload = $validated['tenant'].'|'.strtolower($validated['wp_user']).'|'.$validated['ts'];
        $expected = hash_hmac('sha256', $payload, $secret);

        if (! hash_equals($expected, $validated['sig'])) {
            abort(403, 'Firma non valida.');
        }

        $user = User::findByWpUsername($validated['wp_user']);

        if (! $user || (! $user->isSuperAdmin() && ! $user->belongsToTenant($tenant))) {
            abort(403, 'Utente non autorizzato per questa attività.');
        }

        Auth::login($user);
        $request->session()->regenerate();

        $dest = $request->string('dest')->toString();

        if ($dest === 'promos') {
            return redirect()->route('admin.promos.index', $tenant);
        }

        return redirect()->route('app.home', $tenant);
    }
}
