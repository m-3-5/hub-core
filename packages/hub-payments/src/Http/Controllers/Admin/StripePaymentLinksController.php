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

class StripePaymentLinksController extends Controller
{
    public function index(Tenant $tenant): View|RedirectResponse
    {
        if (! TenantStripeConfig::isConfigured($tenant)) {
            return redirect()
                ->route('admin.services.index', $tenant)
                ->withErrors(['stripe' => 'Configura prima le chiavi Stripe del salone.']);
        }

        $linkedPaymentLinkIds = PayableService::query()
            ->where('tenant_id', $tenant->id)
            ->whereNotNull('stripe_payment_link_id')
            ->pluck('stripe_payment_link_id')
            ->all();

        try {
            $paymentLinks = (new StripePaymentLinkService(TenantStripeConfig::secretKey($tenant)))->listPaymentLinks();
        } catch (RuntimeException $e) {
            return redirect()
                ->route('admin.services.index', $tenant)
                ->withErrors(['stripe' => $e->getMessage()]);
        }

        return view('hub-payments::admin.services.payment-links', [
            'tenant' => $tenant,
            'paymentLinks' => $paymentLinks,
            'linkedPaymentLinkIds' => $linkedPaymentLinkIds,
        ]);
    }

    public function deactivate(Tenant $tenant, string $link): RedirectResponse
    {
        if (! TenantStripeConfig::isConfigured($tenant)) {
            return back()->withErrors(['stripe' => 'Configura prima le chiavi Stripe del salone.']);
        }

        try {
            (new StripePaymentLinkService(TenantStripeConfig::secretKey($tenant)))->deactivatePaymentLink($link);
        } catch (RuntimeException $e) {
            return back()->withErrors(['stripe' => $e->getMessage()]);
        }

        PayableService::query()
            ->where('tenant_id', $tenant->id)
            ->where('stripe_payment_link_id', $link)
            ->update(['published_to_site' => false]);

        return back()->with('status', 'Link di pagamento disattivato.');
    }
}
