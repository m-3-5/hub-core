<?php

namespace M35\HubPayments\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use M35\HubPayments\Services\StripeConnectService;
use M35\HubPayments\Support\SellerTerms;
use M35\HubPayments\Support\TenantConnect;
use RuntimeException;

/** «Pagamenti protetti»: il venditore si collega a Stripe Connect per ricevere i soldi dalle vendite sul canale hub. */
class ConnectController extends Controller
{
    /** Crea l'account (se serve) e manda alla procedura guidata di Stripe. */
    public function start(Request $request, Tenant $tenant): RedirectResponse
    {
        if (! StripeConnectService::isAvailable()) {
            return back()->withErrors(['connect' => 'I pagamenti protetti non sono ancora attivi: scrivici e li abilitiamo.']);
        }

        // Prima di vendere si accettano le condizioni economiche (chi sopporta commissioni, rimborsi e contestazioni).
        if (! SellerTerms::accepted($tenant)) {
            if (! $request->boolean('accept_terms')) {
                return back()->withErrors(['connect' => 'Per collegare i pagamenti protetti devi prima accettare le condizioni economiche.']);
            }

            SellerTerms::accept($tenant, $request->user(), $request->ip());
        }

        $stripe = StripeConnectService::make();

        try {
            if (! TenantConnect::accountId($tenant)) {
                $email = $tenant->users()->first()?->email;
                $email = $email && ! str_ends_with($email, '@guest.hub-core.local') ? $email : null;

                $account = $stripe->createExpressAccount($tenant->id, $tenant->name, $email);
                TenantConnect::setAccount($tenant, (string) $account['id']);
            }

            $url = $stripe->onboardingLink(
                (string) TenantConnect::accountId($tenant),
                route('admin.connect.refresh', $tenant),
                route('admin.connect.return', $tenant),
            );
        } catch (RuntimeException $e) {
            Log::warning('Stripe Connect: avvio collegamento fallito', ['tenant' => $tenant->slug, 'message' => $e->getMessage()]);

            return back()->withErrors(['connect' => 'Non sono riuscito ad aprire la procedura di Stripe: riprova tra poco.']);
        }

        return redirect()->away($url);
    }

    /** Stripe rimanda qui quando il link è scaduto: ne creiamo uno nuovo senza far ricominciare. */
    public function refresh(Request $request, Tenant $tenant): RedirectResponse
    {
        return $this->start($request, $tenant);
    }

    /** Ritorno dalla procedura guidata: rilegge lo stato dell'account e lo mostra. */
    public function return(Tenant $tenant): RedirectResponse
    {
        $accountId = TenantConnect::accountId($tenant);

        if ($accountId && StripeConnectService::isAvailable()) {
            try {
                TenantConnect::sync($tenant, StripeConnectService::make()->retrieve($accountId));
            } catch (RuntimeException $e) {
                Log::warning('Stripe Connect: lettura stato fallita', ['tenant' => $tenant->slug, 'message' => $e->getMessage()]);
            }
        }

        $tenant->refresh();

        return redirect()
            ->route('admin.services.index', $tenant)
            ->with('status', TenantConnect::isReady($tenant)
                ? 'Collegamento completato: ora puoi ricevere i pagamenti protetti!'
                : 'Hai salvato i dati, ma Stripe ne chiede ancora alcuni: riprendi da «Completa i dati».');
    }

    /** Aggiorna lo stato su richiesta (dopo che Stripe ha verificato i documenti). */
    public function sync(Tenant $tenant): RedirectResponse
    {
        return $this->return($tenant);
    }

    /** Pagina Stripe con saldo e bonifici ricevuti. */
    public function dashboard(Tenant $tenant): RedirectResponse
    {
        $accountId = TenantConnect::accountId($tenant);

        abort_unless($accountId, 404);

        try {
            return redirect()->away(StripeConnectService::make()->dashboardLink($accountId));
        } catch (RuntimeException) {
            return back()->withErrors(['connect' => 'Non riesco ad aprire la tua pagina Stripe adesso: completa prima il collegamento.']);
        }
    }
}
