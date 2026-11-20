<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\EmailLogin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** «Entra con un link via email»: niente password. Per i privati (annunci, buoni dei giochi). */
class EmailLoginController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $next = EmailLogin::safeNext($request->query('next'));

        if ($request->user()) {
            return redirect()->to($next);
        }

        return view('auth.email-link', ['next' => $next]);
    }

    public function send(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:190'], 'next' => ['nullable', 'string', 'max:500']], [
            'email.required' => 'Scrivi la tua email.',
            'email.email' => 'Questa email non sembra giusta.',
        ]);

        $email = Str::lower(trim($data['email']));
        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

        // Lo staff entra solo con la password. Il messaggio è uguale per tutti (non si scopre chi ha un account).
        if (! $user?->isSuperAdmin()) {
            EmailLogin::send($email, $data['next'] ?? '/app');
        }

        return back()->with('sent', $email);
    }

    public function go(Request $request): RedirectResponse
    {
        $data = EmailLogin::read((string) $request->query('t'));
        abort_unless($data, 403);

        $user = EmailLogin::userFor($data['email']);
        abort_if($user->isSuperAdmin(), 403);

        if (! $user->email_verified_at) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->to($data['next']);
    }
}
