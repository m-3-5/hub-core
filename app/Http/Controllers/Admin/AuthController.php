<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return $this->redirectAfterLogin(Auth::user());
        }

        return view('admin.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        if (! Auth::attempt(
            $request->only('email', 'password'),
            $request->boolean('remember')
        )) {
            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors(['email' => 'Email o password non validi.']);
        }

        $request->session()->regenerate();

        return $this->redirectAfterLogin(Auth::user());
    }

    public function showForgotPassword(): View
    {
        return view('admin.password.forgot');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_LINK_SENT) {
            $message = 'Ti abbiamo inviato un\'email con le istruzioni per accedere.';

            if (config('mail.default') === 'log') {
                $message .= ' In locale trovi il link in storage/logs/laravel.log';
            }

            return back()->with('status', $message);
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => 'Non troviamo un account con questa email.']);
    }

    public function showResetPassword(Request $request, string $token): View
    {
        return view('admin.password.reset', [
            'token' => $token,
            'email' => $request->string('email')->toString(),
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $resetUser = null;

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) use (&$resetUser) {
                $resetUser = $user;
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            // Il link arrivato per email prova che l'account è suo: entra subito, senza rifare il login.
            Auth::login($resetUser);
            $request->session()->regenerate();

            return $this->redirectAfterLogin($resetUser)
                ->with('success', 'Password impostata! Sei dentro.');
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => __($status)]);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('welcome');
    }

    private function redirectAfterLogin(User $user): RedirectResponse
    {
        $tenants = $user->accessibleTenants();

        if ($tenants->count() === 1) {
            $tenant = $tenants->first();

            // Chi si è registrato per farsi il sito (es. da piramide35.com) va dritto alla creazione del sito, finché non c'è.
            if (($tenant->settings['first_module'] ?? null) === 'website' && ! $tenant->generatedSite && $tenant->hasPremiumFeatures()) {
                return redirect()->route('admin.sitebuilder.show', $tenant);
            }

            return redirect()->route('app.home', $tenant);
        }

        if ($tenants->isNotEmpty()) {
            return redirect()->route('app.index');
        }

        if ($user->isSuperAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        Auth::logout();

        return redirect()->route('admin.login')
            ->withErrors(['email' => 'Il tuo account non è collegato ad alcuna attività.']);
    }
}
