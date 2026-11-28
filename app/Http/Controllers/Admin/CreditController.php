<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantUsageEntry;
use App\Services\HubBillingService;
use App\Support\CardSetup;
use App\Support\CreditTopUp;
use App\Support\Packages;
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
            'credit' => PayPerUse::spendableCents($tenant),
            'cardEnabled' => PayPerUse::cardEnabled(),
            'gameLocked' => PayPerUse::gameLockedCents($tenant),
            'gameFree' => PayPerUse::gameFreeCents(),
            'premiumOn' => Packages::enabled(),
            'premium' => Packages::state($tenant),
            'package' => Packages::current($tenant),
            'packageItemsLeft' => Packages::itemsLeft($tenant),
            'premiumExit' => Packages::exitActive($tenant),
            'plans' => Packages::plans(),
            'monthOptions' => Packages::monthOptions(),
            'overCents' => Packages::overCents(),
            'exitCents' => Packages::exitCents(),
            'overdraft' => PayPerUse::overdraftCents(),
            'topUpMin' => PayPerUse::topUpMinCents(),
            'canPayPending' => $pending ? PayPerUse::canPay($tenant, PayPerUse::priceCents($tenant, $pending['kind'])) : false,
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
        if (! PayPerUse::cardEnabled()) {
            return back()->withErrors(['credit' => 'Si paga con il credito: ricarica da '.PayPerUse::euro(PayPerUse::topUpMinCents()).' in su.']);
        }

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

    /** Ricarica il credito con la carta: pagamento subito, minimo 5 €. */
    public function topUp(Request $request, Tenant $tenant): RedirectResponse
    {
        $min = PayPerUse::topUpMinCents();
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:'.($min / 100), 'max:500']], [
            'amount.min' => 'La ricarica minima è '.PayPerUse::euro($min).'.',
        ]);
        $secretKey = config('services.hub_billing.secret_key');

        if (! $secretKey) {
            return back()->withErrors(['credit' => 'I pagamenti con la carta non sono ancora attivi: scrivici per il bonifico.']);
        }

        try {
            $session = (new HubBillingService($secretKey))->createTopUpSession(
                $tenant, (int) round(((float) $data['amount']) * 100), route('admin.credit.topup.return', $tenant), route('admin.credit.show', $tenant),
            );
        } catch (RuntimeException $e) {
            report($e);

            return back()->withErrors(['credit' => 'Non riesco ad aprire la pagina di pagamento: riprova tra un momento.']);
        }

        return redirect()->away($session['url']);
    }

    /** Ritorno dalla ricarica: si controlla con Stripe e si pubblica ciò che aspettava. */
    public function topUpReturn(Request $request, Tenant $tenant): RedirectResponse
    {
        CreditTopUp::fromStripe((string) $request->query('session_id', ''), $tenant);
        $tenant->refresh();

        if ($done = PendingPublication::resume($tenant)) {
            return $done;
        }

        return redirect()->route('admin.credit.show', $tenant)->with('status', 'Ricarica fatta: credito '.PayPerUse::euro(PayPerUse::creditCents($tenant)).'. Ti abbiamo mandato la ricevuta per email.');
    }

    /** Max segna una ricarica arrivata con bonifico o in contanti (solo staff). */
    public function manualTopUp(Request $request, Tenant $tenant): RedirectResponse
    {
        abort_unless((bool) $request->user()?->isSuperAdmin(), 403);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:5000'],
            'method' => ['required', 'in:bonifico,contanti,regalo'],
        ]);

        PayPerUse::topUp($tenant, (int) round(((float) $data['amount']) * 100), $data['method'], 'manual-'.now()->format('YmdHis').'-'.$request->user()->id);

        return back()->with('status', 'Credito aggiunto: ora '.PayPerUse::euro(PayPerUse::creditCents($tenant->fresh())).'. Ricevuta inviata al cliente e a te.');
    }

    /** Compra un pacchetto (Base, Premium, Business) per 1, 3, 6 o 12 mesi: si scala dal credito. */
    public function premium(Request $request, Tenant $tenant): RedirectResponse
    {
        abort_unless(Packages::enabled(), 404);

        $data = $request->validate([
            'plan' => ['required', 'in:'.implode(',', array_keys(Packages::plans()))],
            'months' => ['required', 'integer', 'in:'.implode(',', array_keys(Packages::monthOptions()))],
        ]);
        $months = (int) $data['months'];

        if (! Packages::buy($tenant, $data['plan'], $months)) {
            return back()->withErrors(['credit' => 'Per il pacchetto '.Packages::plan($data['plan'])['label'].' servono '.PayPerUse::euro(Packages::priceCents($data['plan'], $months)).' di credito: ricarica qui sotto e riprova.']);
        }

        if ($done = PendingPublication::resume($tenant->fresh())) {
            return $done;
        }

        $until = \Illuminate\Support\Carbon::parse(Packages::state($tenant->fresh())['until'])->timezone(config('app.timezone'))->format('d/m/Y');

        return back()->with('status', '⭐ Pacchetto '.Packages::plan($data['plan'])['label'].' attivo fino al '.$until.'. Si rinnova da solo dal credito, lo fermi quando vuoi.');
    }

    /** Ferma o riprende il rinnovo del pacchetto. */
    public function premiumRenewal(Request $request, Tenant $tenant): RedirectResponse
    {
        Packages::setRenew($tenant, $request->boolean('renew'));

        return back()->with('status', $request->boolean('renew')
            ? 'Il pacchetto si rinnoverà da solo dal credito.'
            : 'Fatto: il pacchetto resta attivo fino alla scadenza, poi per un mese paghi '.PayPerUse::euro(Packages::exitCents()).' a pubblicazione.');
    }

    /** Pubblica subito ciò che aspettava, se ora si può. */
    public function publishPending(Tenant $tenant): RedirectResponse
    {
        return PendingPublication::resume($tenant)
            ?? redirect()->route('admin.credit.show', $tenant)->withErrors(['credit' => 'Prima ricarica il credito.']);
    }
}
