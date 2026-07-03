<?php

namespace M35\HubPayments\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use M35\HubPayments\Models\PayableService;
use M35\HubPayments\Services\StripePaymentLinkService;
use M35\HubPayments\Support\TenantStripeConfig;
use RuntimeException;

class StripeCatalogController extends Controller
{
    public function index(Tenant $tenant): View|RedirectResponse
    {
        if (! TenantStripeConfig::isConfigured($tenant)) {
            return redirect()
                ->route('admin.services.index', $tenant)
                ->withErrors(['stripe' => 'Configura prima le chiavi Stripe del salone.']);
        }

        $linkedProductIds = PayableService::query()
            ->where('tenant_id', $tenant->id)
            ->whereNotNull('stripe_product_id')
            ->pluck('stripe_product_id')
            ->all();

        $secretKey = TenantStripeConfig::secretKey($tenant);

        try {
            $products = (new StripePaymentLinkService($secretKey))->listProducts();
        } catch (RuntimeException $e) {
            return redirect()
                ->route('admin.services.index', $tenant)
                ->withErrors(['stripe' => $e->getMessage()]);
        }

        return view('hub-payments::admin.services.stripe-catalog', [
            'tenant' => $tenant,
            'products' => $products,
            'linkedProductIds' => $linkedProductIds,
            'dashboardBase' => str_starts_with($secretKey, 'sk_test_')
                ? 'https://dashboard.stripe.com/test'
                : 'https://dashboard.stripe.com',
        ]);
    }

    public function archive(Tenant $tenant, string $product): RedirectResponse
    {
        if (! TenantStripeConfig::isConfigured($tenant)) {
            return back()->withErrors(['stripe' => 'Configura prima le chiavi Stripe del salone.']);
        }

        try {
            (new StripePaymentLinkService(TenantStripeConfig::secretKey($tenant)))->archiveProduct($product);
        } catch (RuntimeException $e) {
            return back()->withErrors(['stripe' => $e->getMessage()]);
        }

        return back()->with('status', 'Prodotto archiviato su Stripe.');
    }
}
