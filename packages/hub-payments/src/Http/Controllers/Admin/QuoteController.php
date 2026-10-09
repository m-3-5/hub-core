<?php

namespace M35\HubPayments\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use M35\HubPayments\Models\PayableService;
use M35\HubPayments\Support\QuoteService;
use M35\HubPayments\Support\TenantStripeConfig;
use M35\HubPayments\Support\TenantStripeWebhook;
use RuntimeException;

/** Sezione "Preventivi" del pannello: importo libero, link Stripe pagabile una volta sola. */
class QuoteController extends Controller
{
    public function index(Tenant $tenant): View
    {
        $quotes = PayableService::query()
            ->where('tenant_id', $tenant->id)
            ->where('type', 'quote')
            ->where('status', '!=', 'archived')
            ->latest('id')
            ->get();

        return view('hub-payments::admin.quotes.index', [
            'tenant' => $tenant,
            'quotes' => $quotes,
            'stripeConfigured' => TenantStripeConfig::isConfigured($tenant),
            'webhookConfigured' => TenantStripeWebhook::isConfigured($tenant),
        ]);
    }

    public function store(Request $request, Tenant $tenant): RedirectResponse
    {
        if (! TenantStripeConfig::isConfigured($tenant)) {
            return back()->withErrors(['stripe' => 'Configura prima le chiavi Stripe nella sezione Servizi.']);
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'amount' => ['required', 'numeric', 'min:'.(QuoteService::MIN_CENTS / 100), 'max:'.(QuoteService::MAX_CENTS / 100)],
            'customer_name' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            QuoteService::create(
                $tenant,
                $data['title'],
                (int) round(((float) $data['amount']) * 100),
                $data['customer_name'] ?? null,
                $data['description'] ?? null,
                auth()->user()?->name,
                auth()->id(),
            );
        } catch (RuntimeException $e) {
            Log::warning('Creazione preventivo fallita', ['tenant' => $tenant->slug, 'message' => $e->getMessage()]);

            return back()->withInput()->withErrors(['stripe' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.quotes.index', $tenant)
            ->with('status', 'Preventivo creato: copia il link e mandalo al cliente. Si può pagare una volta sola.');
    }

    public function destroy(Tenant $tenant, PayableService $service): RedirectResponse
    {
        abort_unless($service->tenant_id === $tenant->id && $service->type === 'quote', 404);

        if ($service->isPaid()) {
            return back()->withErrors(['stripe' => 'Un preventivo già pagato non si può annullare.']);
        }

        QuoteService::cancel($tenant, $service);

        return redirect()
            ->route('admin.quotes.index', $tenant)
            ->with('status', 'Preventivo annullato e link di pagamento disattivato.');
    }
}
