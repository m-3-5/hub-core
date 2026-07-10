<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use App\Notifications\TenantWelcomeNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class GuestController extends Controller
{
    public function start(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:azienda,privato,ente'],
            'company_name' => ['required', 'string', 'max:120'],
        ]);

        $tenant = Tenant::create([
            'name' => $validated['company_name'],
            'type' => $validated['type'],
            'slug' => $this->uniqueTenantSlug($validated['company_name']),
            'plan' => 'demo',
            'subscription_status' => $validated['type'] === 'privato' ? 'free' : 'trialing',
            'trial_ends_at' => $validated['type'] === 'privato' ? null : now()->addDays(config('services.hub_billing.trial_days', 30)),
            'guest_verified_at' => null,
        ]);

        $user = User::create([
            'name' => $validated['company_name'],
            'email' => 'guest-'.Str::uuid().'@guest.hub-core.local',
            'password' => Str::random(40),
        ]);

        $tenant->users()->attach($user->id, ['role' => 'admin']);

        Auth::login($user);

        return redirect()->route('app.home', $tenant);
    }

    public function confirmPublish(string $token): RedirectResponse
    {
        $tenant = Tenant::where('guest_email_token', $token)->first();

        if (! $tenant || ($tenant->guest_email_token_expires_at && $tenant->guest_email_token_expires_at->isPast())) {
            return redirect()->route('welcome')->withErrors(['guest' => 'Link non valido o scaduto.']);
        }

        $tenant->update([
            'guest_verified_at' => now(),
            'guest_email_token' => null,
            'guest_email_token_expires_at' => null,
        ]);

        $webhook = app(\App\Services\WordPressWebhookDispatcher::class);

        foreach ($tenant->promos()->where('status', 'draft')->get() as $promo) {
            $promo->update(['status' => 'published', 'published_at' => now()]);
            $webhook->promoPublished($tenant, $promo->fresh());
        }

        $user = $tenant->users()->first();
        $passwordToken = Password::broker()->createToken($user);
        $user->notify(new TenantWelcomeNotification($tenant, $passwordToken));

        return redirect()->route('admin.password.reset', [
            'token' => $passwordToken,
            'email' => $user->email,
        ])->with('success', 'Email confermata! La tua promo è online — imposta la password per accedere sempre alla tua area.');
    }

    private function uniqueTenantSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'attivita';
        $slug = $base;
        $i = 2;

        while (Tenant::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }
}
