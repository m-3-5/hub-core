<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * /crea-sito: pagina pubblica «Crea il tuo sito con l'IA» (anche dal tasto «Crealo da solo» di piramide35.com,
 * con ?ref=piramide35). Il pulsante porta alla registrazione già impostata per il sito, oppure dritto
 * alla creazione del sito se la persona è già dentro con la sua attività.
 */
class SiteStartController extends Controller
{
    public function show(Request $request): View
    {
        $ref = RegistrationController::cleanSource($request->query('ref'));
        $user = $request->user();
        $tenant = $user && ! $user->isSuperAdmin()
            ? $user->accessibleTenants()->first(fn ($t) => in_array($t->type ?: 'azienda', ['azienda', 'ente'], true))
            : null;

        $startUrl = $tenant
            ? route('admin.sitebuilder.show', $tenant)
            : route('registration.create', array_filter(['per' => 'sito', 'ref' => $ref]));

        return view('site-start', [
            'startUrl' => $startUrl,
            'loggedIn' => (bool) $tenant,
            'freeDays' => (int) config('services.hub_billing.free_days', 30),
            'trialDays' => (int) config('services.hub_billing.trial_days', 90),
            'launchEur' => (int) config('services.hub_billing.launch_offer_price_eur', 1),
            'premiumMonthly' => (int) config('services.hub_billing.tiers.premium.monthly', 19),
        ]);
    }
}
