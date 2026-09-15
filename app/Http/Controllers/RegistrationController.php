<?php

namespace App\Http\Controllers;

use App\Models\PendingRegistration;
use App\Models\Tenant;
use App\Models\TenantModuleCharge;
use App\Models\User;
use App\Notifications\ConfirmRegistrationNotification;
use App\Notifications\TenantWelcomeNotification;
use App\Services\HubBillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class RegistrationController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $email = $request->string('email')->toString();
        $resume = $this->resumeAbandonedPayment($email);

        if ($resume) {
            return $resume;
        }

        $validated = $request->validate([
            'type' => ['required', 'in:azienda,privato,ente'],
            'first_module' => ['required_unless:type,privato', 'nullable', 'in:promo,services'],
            'company_name' => ['required', 'string', 'max:120'],
            'contact_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email', 'unique:pending_registrations,email'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $pending = PendingRegistration::create([
            'token' => Str::random(48),
            'type' => $validated['type'],
            'first_module' => $validated['first_module'] ?? null,
            'name' => $validated['company_name'],
            'contact_name' => $validated['contact_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'expires_at' => now()->addHours(48),
        ]);

        try {
            Notification::route('mail', $pending->email)->notify(new ConfirmRegistrationNotification($pending));
        } catch (\Throwable $e) {
            $pending->delete();

            return back()->withInput()->withErrors([
                'email' => 'Non sono riuscito a inviare l\'email di conferma a questo indirizzo. Controlla che sia scritto correttamente e riprova.',
            ]);
        }

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

        $isPrivato = $pending->type === 'privato';

        $tenant = Tenant::create([
            'name' => $pending->name,
            'type' => $pending->type,
            'slug' => $this->uniqueTenantSlug($pending->name),
            'phone' => $pending->phone,
            'plan' => 'demo',
            'trial_ends_at' => null,
            'subscription_status' => $isPrivato ? 'free' : 'pending_payment',
            'settings' => $pending->first_module ? ['first_module' => $pending->first_module] : null,
        ]);

        $user = User::create([
            'name' => $pending->contact_name,
            'email' => $pending->email,
            'password' => Str::random(40),
        ]);

        $tenant->users()->attach($user->id, ['role' => 'admin']);

        if (! $isPrivato) {
            $secretKey = config('services.hub_billing.secret_key');

            if ($secretKey) {
                try {
                    $session = (new HubBillingService($secretKey))
                        ->createLaunchOfferCheckoutSession($tenant, $pending->first_module ?? 'promo');

                    $pending->delete();

                    return redirect()->away($session['url']);
                } catch (Throwable $e) {
                    Log::warning('Offerta di lancio non disponibile, ripiego sulla prova gratuita', [
                        'tenant_id' => $tenant->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Stripe non configurato o non raggiungibile: non blocchiamo la registrazione,
            // ripieghiamo sulla prova gratuita di 30 giorni come prima di questa offerta.
            $tenant->update([
                'subscription_status' => 'trialing',
                'trial_ends_at' => now()->addDays(config('services.hub_billing.trial_days', 30)),
            ]);
        }

        $passwordToken = Password::broker()->createToken($user);

        try {
            $user->notify(new TenantWelcomeNotification($tenant, $passwordToken));
        } catch (Throwable $e) {
            report($e);
        }

        $pending->delete();

        return redirect()->route('admin.password.reset', [
            'token' => $passwordToken,
            'email' => $user->email,
        ])->with('success', 'Email confermata! Imposta la tua password per iniziare.');
    }

    /**
     * Se questa email appartiene già a un tenant che ha confermato la registrazione
     * ma ha abbandonato il pagamento dell'offerta di lancio (pending_payment), lo
     * rimandiamo a un nuovo checkout invece di bloccarlo con "email già in uso".
     */
    private function resumeAbandonedPayment(string $email): ?RedirectResponse
    {
        if ($email === '') {
            return null;
        }

        $user = User::where('email', $email)->first();
        $tenant = $user?->tenants()->where('subscription_status', 'pending_payment')->first();

        if (! $tenant) {
            return null;
        }

        $secretKey = config('services.hub_billing.secret_key');

        if (! $secretKey) {
            return null;
        }

        try {
            $module = $tenant->settings['first_module'] ?? 'promo';
            $session = (new HubBillingService($secretKey))->createLaunchOfferCheckoutSession($tenant, $module);

            return redirect()->away($session['url']);
        } catch (Throwable $e) {
            Log::warning('Ripresa pagamento offerta di lancio fallita', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
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
