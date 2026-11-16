<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantUsageEntry;
use App\Services\HubBillingService;
use App\Support\CardSetup;
use App\Support\PayPerUse;
use App\Support\PendingPublication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/** «Pagamenti»: la carta, cosa costa pubblicare e cosa verrà addebitato (dopo un mese, tutto insieme). */
class CreditController extends Controller
{
    public function show(Tenant $tenant): View
    {
        $pending = PendingPublication::current($tenant);

        return view('admin.credit.show', [
            'tenant' => $tenant,
            'hasCard' => PayPerUse::hasCard($tenant),
            'card' => $tenant->settings['ppu_card'] ?? null,
            'pending' => $pending,
            'pendingPrice' => $pending ? PayPerUse::priceCents($tenant, $pending['kind']) : null,
            'prices' => collect(PayPerUse::KINDS)->map(fn ($label, $kind) => PayPerUse::priceCents($tenant, $kind))->all(),
            'toBill' => PayPerUse::pendingCents($tenant),
            'nextBill' => TenantUsageEntry::where('tenant_id', $tenant->id)->whereIn('status', ['pending', 'failed'])->min('bill_after'),
            'entries' => TenantUsageEntry::where('tenant_id', $tenant->id)->latest('id')->limit(20)->get(),
            'billDays' => (int) config('services.hub_billing.pay_per_use.bill_after_days', 30),
        ]);
    }

    /** Salva la carta su Stripe: nessun addebito adesso. */
    public function checkout(Tenant $tenant): RedirectResponse
    {
        $secretKey = config('services.hub_billing.secret_key');

        if (! $secretKey) {
            return back()->withErrors(['credit' => 'I pagamenti non sono ancora attivi: riprova più tardi.']);
        }

        try {
            $session = (new HubBillingService($secretKey))->createCardSetupSession(
                $tenant, route('admin.credit.return', $tenant), route('admin.credit.show', $tenant),
            );
        } catch (RuntimeException $e) {
            report($e);

            return back()->withErrors(['credit' => 'Non riesco ad aprire la pagina della carta: riprova tra un momento.']);
        }

        return redirect()->away($session['url']);
    }

    /** Ritorno da Stripe: si controlla la sessione con Stripe e si pubblica ciò che aspettava. */
    public function return(Request $request, Tenant $tenant): RedirectResponse
    {
        CardSetup::fromStripe((string) $request->query('session_id', ''), $tenant);
        $tenant->refresh();

        if (! PayPerUse::hasCard($tenant)) {
            return redirect()->route('admin.credit.show', $tenant)->withErrors(['credit' => 'La carta non risulta salvata: riprova.']);
        }

        if ($done = PendingPublication::resume($tenant)) {
            return $done;
        }

        return redirect()->route('admin.credit.show', $tenant)->with('status', 'Carta salvata. Non paghi niente adesso.');
    }

    /** Pubblica subito ciò che aspettava, se ora si può. */
    public function publishPending(Tenant $tenant): RedirectResponse
    {
        return PendingPublication::resume($tenant)
            ?? redirect()->route('admin.credit.show', $tenant)->withErrors(['credit' => 'Prima aggiungi la carta.']);
    }
}
