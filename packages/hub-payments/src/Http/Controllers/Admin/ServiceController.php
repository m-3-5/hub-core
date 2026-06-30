<?php

namespace M35\HubPayments\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use M35\HubPayments\Models\PayableService;
use M35\HubPayments\Services\StripePaymentLinkService;
use M35\HubPayments\Support\TenantServiceQuota;
use M35\HubPayments\Support\TenantStripeConfig;
use RuntimeException;

class ServiceController extends Controller
{
    public function index(Tenant $tenant): View
    {
        $services = PayableService::query()
            ->where('tenant_id', $tenant->id)
            ->where('type', 'service')
            ->latest()
            ->get();

        return view('hub-payments::admin.services.index', [
            'tenant' => $tenant,
            'services' => $services,
            'stripeConfigured' => TenantStripeConfig::isConfigured($tenant),
            'stripeMasked' => TenantStripeConfig::maskedSecret($tenant),
            'quota' => [
                'included' => TenantServiceQuota::includedLimit($tenant),
                'used' => TenantServiceQuota::usedCount($tenant),
                'remaining' => TenantServiceQuota::remaining($tenant),
                'paid_price' => TenantServiceQuota::paidUnlockPrice($tenant),
            ],
        ]);
    }

    public function storeStripeSettings(Request $request, Tenant $tenant): RedirectResponse
    {
        $request->validate([
            'stripe_secret_key' => ['required', 'string', 'min:20'],
            'stripe_publishable_key' => ['nullable', 'string', 'min:20'],
        ]);

        TenantStripeConfig::store(
            $tenant,
            $request->string('stripe_secret_key')->toString(),
            $request->string('stripe_publishable_key')->toString() ?: null,
        );

        return back()->with('status', 'Chiavi Stripe salvate per '.$tenant->name.'.');
    }

    public function create(Tenant $tenant): View|RedirectResponse
    {
        if (! TenantStripeConfig::isConfigured($tenant)) {
            return redirect()
                ->route('admin.services.index', $tenant)
                ->withErrors(['stripe' => 'Configura prima le chiavi Stripe del salone.']);
        }

        return view('hub-payments::admin.services.create', [
            'tenant' => $tenant,
            'quota' => [
                'included' => TenantServiceQuota::includedLimit($tenant),
                'used' => TenantServiceQuota::usedCount($tenant),
                'remaining' => TenantServiceQuota::remaining($tenant),
                'paid_price' => TenantServiceQuota::paidUnlockPrice($tenant),
            ],
        ]);
    }

    public function store(Request $request, Tenant $tenant): RedirectResponse
    {
        if (! TenantStripeConfig::isConfigured($tenant)) {
            return back()->withErrors(['stripe' => 'Configura le chiavi Stripe prima di creare un link.']);
        }

        if (! TenantServiceQuota::hasIncludedSlot($tenant)) {
            return back()
                ->withInput()
                ->withErrors([
                    'quota' => 'Hai usato i '.TenantServiceQuota::includedLimit($tenant).' servizi inclusi nella demo. '
                        .'Per crearne altri servirà il pacchetto a pagamento (€'.TenantServiceQuota::paidUnlockPrice($tenant).'/mese o per servizio — pagamento in arrivo).',
                ]);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'amount' => ['required', 'numeric', 'min:0.50', 'max:99999'],
            'published_to_site' => ['boolean'],
        ]);

        $amountCents = (int) round(((float) $validated['amount']) * 100);
        $secretKey = TenantStripeConfig::secretKey($tenant);

        try {
            $stripe = new StripePaymentLinkService($secretKey);
            $result = $stripe->createPaymentLink(
                $validated['title'],
                $validated['description'] ?? null,
                $amountCents,
                config('hub-payments.currency', 'eur'),
            );
        } catch (RuntimeException $e) {
            return back()
                ->withInput()
                ->withErrors(['stripe' => $e->getMessage()]);
        }

        $service = PayableService::create([
            'tenant_id' => $tenant->id,
            'created_by' => auth()->id(),
            'type' => 'service',
            'title' => $validated['title'],
            'slug' => PayableService::uniqueSlugForTenant($tenant->id, $validated['title']),
            'description' => $validated['description'] ?? null,
            'amount_cents' => $amountCents,
            'currency' => config('hub-payments.currency', 'eur'),
            'stripe_product_id' => $result['product_id'],
            'stripe_price_id' => $result['price_id'],
            'stripe_payment_link_id' => $result['payment_link_id'],
            'payment_url' => $result['url'],
            'status' => 'active',
            'published_to_site' => $request->boolean('published_to_site'),
        ]);

        return redirect()
            ->route('admin.services.show', [$tenant, $service])
            ->with('status', 'Link di pagamento creato su Stripe (carta + Klarna se attivo sul conto).');
    }

    public function show(Tenant $tenant, PayableService $service): View
    {
        abort_unless($service->tenant_id === $tenant->id && $service->type === 'service', 404);

        return view('hub-payments::admin.services.show', compact('tenant', 'service'));
    }

    public function togglePublish(Tenant $tenant, PayableService $service): RedirectResponse
    {
        abort_unless($service->tenant_id === $tenant->id, 404);

        $service->update(['published_to_site' => ! $service->published_to_site]);

        return back()->with('status', $service->published_to_site
            ? 'Servizio visibile sul sito (API).'
            : 'Servizio nascosto dal sito.');
    }
}
