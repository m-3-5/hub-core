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
    /** Registrazione a passi, una domanda per schermata (come la creazione guidata delle promo). */
    public function create(Request $request): View
    {
        // Da piramide35.com / pagina «Crea il tuo sito»: /registrati?per=sito&ref=piramide35
        $forSite = $request->query('per') === 'sito';

        return view('registration.wizard', [
            'forSite' => $forSite,
            'source' => self::cleanSource($request->query('ref')),
        ]);
    }

    /** Solo lettere, numeri e trattini: è un'etichetta, non un indirizzo. */
    public static function cleanSource(mixed $ref): ?string
    {
        $ref = is_string($ref) ? strtolower(trim($ref)) : '';

        return preg_match('/^[a-z0-9][a-z0-9_-]{0,39}$/', $ref) ? $ref : null;
    }

    public function store(Request $request): RedirectResponse
    {
        $email = $request->string('email')->toString();
        $resume = $this->resumeAbandonedPayment($email);

        if ($resume) {
            return $resume;
        }

        $validated = $request->validate([
            'type' => ['required', 'in:azienda,privato,ente'],
            'first_module' => ['required_unless:type,privato', 'nullable', 'in:promo,services,website'],
            'company_name' => ['required', 'string', 'max:120'],
            'contact_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email', 'unique:pending_registrations,email'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $pending = PendingRegistration::create([
            'token' => Str::random(48),
            'type' => $validated['type'],
            'first_module' => $validated['type'] === 'privato' ? null : ($validated['first_module'] ?? null),
            'source' => self::cleanSource($request->input('source')),
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

        [$tenant, $user] = self::createAccount(
            type: $pending->type,
            companyName: $pending->name,
            contactName: $pending->contact_name,
            email: $pending->email,
            phone: $pending->phone,
            firstModule: $pending->first_module,
            source: $pending->source,
        );

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

    /**
     * Crea attività + titolare con email già confermata (link via email oppure Google).
     *
     * @return array{0: Tenant, 1: User}
     */
    public static function createAccount(string $type, string $companyName, string $contactName, string $email, ?string $phone = null, ?string $firstModule = null, ?string $source = null): array
    {
        $isPrivato = $type === 'privato';

        $settings = array_filter([
            'first_module' => $isPrivato ? null : $firstModule,
            'source' => $source,
            // Solo chi si registra con il periodo gratuito riceve il promemoria dell'euro di lancio.
            'free_week' => $isPrivato ? null : true,
        ]);

        $tenant = Tenant::create([
            'name' => $companyName,
            'type' => $type,
            'slug' => self::uniqueTenantSlug($companyName),
            'phone' => $phone,
            'plan' => 'demo',
            // Periodo gratuito senza pagare nulla: se non attivano non succede niente (vedi hub:launch-offer-reminders).
            'subscription_status' => $isPrivato ? 'free' : 'trialing',
            'trial_ends_at' => $isPrivato ? null : now()->addDays(config('services.hub_billing.free_days', 30)),
            // Email confermata (link o Google): senza questo il suo sito non andava online.
            'guest_verified_at' => now(),
            'settings' => $settings ?: null,
        ]);

        $user = User::create([
            'name' => $contactName,
            'email' => $email,
            'password' => Str::random(40),
        ]);

        $tenant->users()->attach($user->id, ['role' => 'admin']);

        return [$tenant, $user];
    }

    private static function uniqueTenantSlug(string $companyName): string
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
