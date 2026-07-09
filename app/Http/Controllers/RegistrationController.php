<?php

namespace App\Http\Controllers;

use App\Models\PendingRegistration;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\ConfirmRegistrationNotification;
use App\Notifications\TenantWelcomeNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RegistrationController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:azienda,privato,ente'],
            'company_name' => ['required', 'string', 'max:120'],
            'contact_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email', 'unique:pending_registrations,email'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $pending = PendingRegistration::create([
            'token' => Str::random(48),
            'type' => $validated['type'],
            'name' => $validated['company_name'],
            'contact_name' => $validated['contact_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'expires_at' => now()->addHours(48),
        ]);

        Notification::route('mail', $pending->email)->notify(new ConfirmRegistrationNotification($pending));

        return redirect()
            ->route('welcome')
            ->with('success', 'Controlla '.$validated['email'].' e clicca sul link per confermare la registrazione.');
    }

    public function confirm(string $token): View|RedirectResponse
    {
        $pending = PendingRegistration::where('token', $token)->first();

        if (! $pending || $pending->isExpired()) {
            return redirect()
                ->route('welcome')
                ->withErrors(['registration' => 'Link di conferma non valido o scaduto. Registrati di nuovo.']);
        }

        $tenant = Tenant::create([
            'name' => $pending->name,
            'type' => $pending->type,
            'slug' => $this->uniqueTenantSlug($pending->name),
            'phone' => $pending->phone,
            'plan' => 'demo',
            'trial_ends_at' => now()->addDays(config('services.hub_billing.trial_days', 30)),
            'subscription_status' => 'trialing',
        ]);

        $user = User::create([
            'name' => $pending->contact_name,
            'email' => $pending->email,
            'password' => Str::random(40),
        ]);

        $tenant->users()->attach($user->id, ['role' => 'admin']);

        $passwordToken = Password::broker()->createToken($user);
        $user->notify(new TenantWelcomeNotification($tenant, $passwordToken));

        $pending->delete();

        return redirect()->route('admin.password.reset', [
            'token' => $passwordToken,
            'email' => $user->email,
        ])->with('success', 'Email confermata! Imposta la tua password per iniziare.');
    }

    private function uniqueTenantSlug(string $companyName): string
    {
        $base = Str::slug($companyName) ?: 'azienda';
        $slug = $base;
        $i = 2;

        while (Tenant::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }
}
