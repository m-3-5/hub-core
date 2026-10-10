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

        try {
            $url = ProtectedCheckout::begin(
                $tenant,
                $service,
                $service->type === 'product' ? (int) ($data['quantity'] ?? 1) : 1,
                $data['customer_email'] ?? null,
            );
        } catch (RuntimeException $e) {
            Log::warning('Pagamento protetto: sessione non creata', ['tenant' => $tenant->slug, 'service' => $service->id, 'message' => $e->getMessage()]);

            return back()->withErrors(['buy' => 'Non riesco ad aprire il pagamento in questo momento. Riprova tra poco.']);
        }

        return redirect()->away($url);
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
