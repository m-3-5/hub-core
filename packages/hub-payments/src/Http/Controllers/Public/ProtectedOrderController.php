<?php

namespace M35\HubPayments\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use M35\HubPayments\Models\PayableOrder;
use M35\HubPayments\Models\PayableService;
use M35\HubPayments\Support\ProtectedCheckout;
use M35\HubPayments\Support\ProtectedPayout;
use RuntimeException;

/** Acquisto con pagamento protetto dalle pagine pubbliche di inm35.it e pagina dell'ordine del compratore. */
class ProtectedOrderController extends Controller
{
    public function buy(Request $request, Tenant $tenant, PayableService $service): RedirectResponse
    {
        abort_unless($service->tenant_id === $tenant->id, 404);
        abort_unless(in_array($service->type, ['service', 'product'], true), 404);
        abort_unless($service->status === 'active' && $service->published_to_site, 404);

        $data = $request->validate([
            'quantity' => ['nullable', 'integer', 'min:1', 'max:20'],
            'customer_email' => ['nullable', 'email', 'max:190'],
        ]);

        // Venditore non (ancora) collegato ai pagamenti protetti: resta il link di pagamento di sempre.
        if (! ProtectedCheckout::available($tenant)) {
            $fallback = $service->hubPaymentUrl();

            return $fallback
                ? redirect()->away($fallback)
                : back()->withErrors(['buy' => 'Questo articolo non è acquistabile online al momento.']);
        }

        // Il compratore deve aver letto e accettato le condizioni di acquisto protetto.
        if (! $request->boolean('accept_buyer_terms')) {
            return back()->withErrors(['buy' => 'Per acquistare devi accettare le condizioni di acquisto protetto.'])->withInput();
        }

        try {
            $url = ProtectedCheckout::begin(
                $tenant,
                $service,
                $service->type === 'product' ? (int) ($data['quantity'] ?? 1) : 1,
                $data['customer_email'] ?? null,
                $request->ip(),
            );
        } catch (RuntimeException $e) {
            Log::warning('Pagamento protetto: sessione non creata', ['tenant' => $tenant->slug, 'service' => $service->id, 'message' => $e->getMessage()]);

            return back()->withErrors(['buy' => 'Non riesco ad aprire il pagamento in questo momento. Riprova tra poco.']);
        }

        return redirect()->away($url);
    }

    /** «Ho ricevuto tutto»: conferma e rilascio immediato dei soldi al venditore. */
    public function confirm(string $token): RedirectResponse
    {
        $order = $this->orderByToken($token);

        if (! $order->isHeld()) {
            return redirect()->route('protected.order.show', $token);
        }

        // Prima si registra la conferma (e la scadenza diventa «adesso»): se Stripe non risponde ci pensa il comando orario.
        $order->forceFill(['confirmed_at' => now(), 'release_at' => now()])->save();

        try {
            ProtectedPayout::release($order);
        } catch (RuntimeException $e) {
            Log::warning('Pagamento protetto: rilascio dopo conferma rimandato', ['order' => $order->id, 'message' => $e->getMessage()]);
        }

        return redirect()->route('protected.order.show', $token)->with('status', 'Grazie! Hai confermato: il pagamento viene consegnato al venditore.');
    }

    /** «Segnala un problema»: ferma il rilascio e avvisa venditore e team. */
    public function report(Request $request, string $token): RedirectResponse
    {
        $order = $this->orderByToken($token);
        $data = $request->validate(['message' => ['required', 'string', 'min:10', 'max:1000']], [
            'message.required' => 'Descrivici in poche parole cosa non va.',
            'message.min' => 'Descrivici in poche parole cosa non va (almeno 10 caratteri).',
        ]);

        ProtectedPayout::reportProblem($order, trim(strip_tags($data['message'])));

        return redirect()->route('protected.order.show', $token)->with('status', 'Segnalazione ricevuta: il pagamento resta fermo finché il problema non è risolto.');
    }

    private function orderByToken(string $token): PayableOrder
    {
        return PayableOrder::query()->where('buyer_token', $token)->where('flow', 'protected')->where('status', 'paid')->firstOrFail();
    }

    /** Pagina dell'ordine per il compratore: si apre con il codice segreto del link ricevuto per email. */
    public function show(string $token): View
    {
        $order = PayableOrder::query()->where('buyer_token', $token)->where('flow', 'protected')->firstOrFail();

        return view('hub-payments::public.order', [
            'order' => $order,
            'tenant' => $order->tenant,
            'justPaid' => request()->boolean('pagato'),
        ]);
    }
}
