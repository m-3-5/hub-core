<?php

namespace M35\HubPayments\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\WordPressWebhookDispatcher;
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

        $linkedServices = PayableService::query()
            ->where('tenant_id', $tenant->id)
            ->whereNotNull('stripe_payment_link_id')
            ->get()
            ->keyBy('stripe_payment_link_id');

        try {
            $paymentLinks = (new StripePaymentLinkService(TenantStripeConfig::secretKey($tenant)))->listPaymentLinks();
        } catch (RuntimeException $e) {
            return redirect()
                ->route('admin.services.index', $tenant)
                ->withErrors(['stripe' => $e->getMessage()]);
        }

        $paymentLinks = array_values(array_filter($paymentLinks, fn (array $link) => $link['active'] ?? false));

        return view('hub-payments::admin.services.payment-links', [
            'tenant' => $tenant,
            'paymentLinks' => $paymentLinks,
            'linkedServices' => $linkedServices,
        ]);
    }

    public function import(Tenant $tenant, string $link): RedirectResponse
    {
        if (! TenantStripeConfig::isConfigured($tenant)) {
            return back()->withErrors(['stripe' => 'Configura prima le chiavi Stripe del salone.']);
        }

        try {
            $data = (new StripePaymentLinkService(TenantStripeConfig::secretKey($tenant)))->getPaymentLink($link);
        } catch (RuntimeException $e) {
            return back()->withErrors(['stripe' => $e->getMessage()]);
        }

        $price = $data['line_items']['data'][0]['price'] ?? null;
        $product = is_array($price) ? ($price['product'] ?? null) : null;

        if (! is_array($price) || ! is_array($product)) {
            return back()->withErrors(['stripe' => 'Impossibile leggere prezzo e prodotto per questo link.']);
        }

        $title = $product['name'] ?? 'Servizio Stripe';

        $service = PayableService::create([
            'tenant_id' => $tenant->id,
            'created_by' => auth()->id(),
            'type' => 'service',
            'title' => $title,
            'slug' => PayableService::uniqueSlugForTenant($tenant->id, $title),
            'description' => $product['description'] ?? null,
            'amount_cents' => $price['unit_amount'] ?? 0,
            'currency' => $price['currency'] ?? config('hub-payments.currency', 'eur'),
            'stripe_product_id' => $product['id'] ?? null,
            'stripe_price_id' => $price['id'] ?? null,
            'stripe_payment_link_id' => $data['id'],
            'payment_url' => $data['url'],
            'status' => 'active',
            'published_to_site' => true,
        ]);

        app(WordPressWebhookDispatcher::class)->servicePublished($tenant, $service);

        return redirect()
            ->route('admin.services.edit', [$tenant, $service])
            ->with('status', 'Servizio importato e pubblicato sul sito. Aggiungi una foto se vuoi.');
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

        $updated = PayableService::query()
            ->where('tenant_id', $tenant->id)
            ->where('stripe_payment_link_id', $link)
            ->where('published_to_site', true)
            ->update(['published_to_site' => false]);

        if ($updated > 0) {
            app(WordPressWebhookDispatcher::class)->servicesSync($tenant);
        }

        return back()->with('status', 'Link di pagamento disattivato.');
    }
}
