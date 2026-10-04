<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;

class MagicLoginController extends Controller
{
    /** Link firmato e a scadenza per far entrare un utente di un'azienda senza password. */
    public static function makeUrl(User $user, Tenant $tenant, int $days): string
    {
        return URL::temporarySignedRoute('magic.login', now()->addDays($days), [
            'user' => $user->id,
            'tenant' => $tenant->slug,
            'k' => self::fingerprint($user),
        ]);
    }

    // Cambiare la password dell'utente invalida i link già emessi.
    private static function fingerprint(User $user): string
    {
        return substr(hash('sha256', $user->password.'|'.config('app.key')), 0, 20);
    }

    public function login(Request $request, User $user, Tenant $tenant): RedirectResponse
    {
        abort_unless(
            ! $user->isSuperAdmin()
            && $user->belongsToTenant($tenant)
            && hash_equals(self::fingerprint($user), (string) $request->query('k')),
            403,
        );

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->route('app.home', $tenant);
    }
}
