<?php

namespace M35\HubPayments\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantModuleCharge;
use App\Services\WordPressWebhookDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use M35\HubPayments\Models\PayableService;
use M35\HubPayments\Services\StripePaymentLinkService;
use M35\HubPayments\Support\ImageOptimizer;
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
            ->where('status', '!=', 'archived')
            ->latest()
            ->get();

        return view('hub-payments::admin.services.index', [
            'tenant' => $tenant,
            'services' => $services,
            'stripeConfigured' => TenantStripeConfig::isConfigured($tenant),
            'stripeMasked' => TenantStripeConfig::maskedSecret($tenant),
            'quota' => $this->quotaFor($tenant),
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
            'quota' => $this->quotaFor($tenant),
        ]);
    }

    public function store(Request $request, Tenant $tenant): RedirectResponse
    {
        if (! TenantStripeConfig::isConfigured($tenant)) {
            return back()->withErrors(['stripe' => 'Configura le chiavi Stripe prima di creare un link.']);
        }

        $overQuota = ! TenantServiceQuota::hasIncludedSlot($tenant);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'amount' => ['required', 'numeric', 'min:0.50', 'max:99999'],
            'cover_image' => ['nullable', 'image', 'max:5120'],
            'published_to_site' => ['boolean'],
        ]);

        $amountCents = (int) round(((float) $validated['amount']) * 100);
        $secretKey = TenantStripeConfig::secretKey($tenant);
        $coverImagePath = $this->storeCoverImage($request, $tenant);

        try {
            $stripe = new StripePaymentLinkService($secretKey);
            $result = $stripe->createPaymentLink(
                $validated['title'],
                $validated['description'] ?? null,
                $amountCents,
                config('hub-payments.currency', 'eur'),
                $coverImagePath ? url(Storage::disk('public')->url($coverImagePath)) : null,
            );
        } catch (RuntimeException $e) {
            if ($coverImagePath) {
                Storage::disk('public')->delete($coverImagePath);
            }

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
            'cover_image_path' => $coverImagePath,
            'amount_cents' => $amountCents,
            'currency' => config('hub-payments.currency', 'eur'),
            'stripe_product_id' => $result['product_id'],
            'stripe_price_id' => $result['price_id'],
            'stripe_payment_link_id' => $result['payment_link_id'],
            'payment_url' => $result['url'],
            'status' => 'active',
            'published_to_site' => $request->boolean('published_to_site'),
        ]);

        if ($service->published_to_site) {
            app(WordPressWebhookDispatcher::class)->servicePublished($tenant, $service);
        }

        $status = 'Link di pagamento creato su Stripe (carta + metodi extra attivi sul conto: Klarna, Scalapay, ecc.).';

        if ($overQuota) {
            TenantModuleCharge::create([
                'tenant_id' => $tenant->id,
                'module' => 'servizi',
                'charge_type' => 'extra_item',
                'period' => now()->format('Y-m'),
                'description' => 'Servizio "'.$service->title.'" oltre la quota inclusa (creato dal cliente)',
                'amount_cents' => config('module_pricing.servizi.extra_self_cents', 1200),
                'paid' => false,
            ]);

            $status .= ' Hai superato i '.TenantServiceQuota::includedLimit($tenant).' servizi inclusi: questo è stato registrato come extra a pagamento.';
        }

        return redirect()
            ->route('admin.services.show', [$tenant, $service])
            ->with('status', $status);
    }

    public function show(Tenant $tenant, PayableService $service): View
    {
        abort_unless($service->tenant_id === $tenant->id && $service->type === 'service', 404);

        return view('hub-payments::admin.services.show', compact('tenant', 'service'));
    }

    public function edit(Tenant $tenant, PayableService $service): View
    {
        abort_unless($service->tenant_id === $tenant->id && $service->type === 'service', 404);

        return view('hub-payments::admin.services.edit', compact('tenant', 'service'));
    }

    public function update(Request $request, Tenant $tenant, PayableService $service): RedirectResponse
    {
        abort_unless($service->tenant_id === $tenant->id && $service->type === 'service', 404);

        if (! TenantStripeConfig::isConfigured($tenant)) {
            return back()->withErrors(['stripe' => 'Configura le chiavi Stripe prima di modificare il servizio.']);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'amount' => ['required', 'numeric', 'min:0.50', 'max:99999'],
            'cover_image' => ['nullable', 'image', 'max:5120'],
            'remove_cover_image' => ['boolean'],
            'published_to_site' => ['boolean'],
        ]);

        $amountCents = (int) round(((float) $validated['amount']) * 100);
        $secretKey = TenantStripeConfig::secretKey($tenant);
        $coverImagePath = $service->cover_image_path;

        if ($request->boolean('remove_cover_image') && $coverImagePath) {
            Storage::disk('public')->delete($coverImagePath);
            $coverImagePath = null;
        }

        if ($request->hasFile('cover_image')) {
            if ($coverImagePath) {
                Storage::disk('public')->delete($coverImagePath);
            }

            $coverImagePath = $this->storeCoverImage($request, $tenant);
        }

        $stripeImageUrl = $coverImagePath ? url(Storage::disk('public')->url($coverImagePath)) : null;

        try {
            $stripe = new StripePaymentLinkService($secretKey);

            if ($service->stripe_product_id) {
                $stripe->updateProduct(
                    $service->stripe_product_id,
                    $validated['title'],
                    $validated['description'] ?? null,
                    $stripeImageUrl,
                );
            }

            $priceChanged = $amountCents !== $service->amount_cents;
            $newPriceId = $service->stripe_price_id;

            if ($priceChanged && $service->stripe_product_id) {
                $price = $stripe->createPrice(
                    $service->stripe_product_id,
                    $amountCents,
                    $service->currency,
                );
                $newPriceId = $price['id'];
            }

            if ($priceChanged && $service->stripe_payment_link_id && $newPriceId) {
                $stripe->updatePaymentLinkPrice($service->stripe_payment_link_id, $newPriceId);
            }

            $titleChanged = $validated['title'] !== $service->title;

            $service->update([
                'title' => $validated['title'],
                'slug' => $titleChanged
                    ? PayableService::uniqueSlugForTenant($tenant->id, $validated['title'], $service->id)
                    : $service->slug,
                'description' => $validated['description'] ?? null,
                'cover_image_path' => $coverImagePath,
                'amount_cents' => $amountCents,
                'stripe_price_id' => $newPriceId,
                'published_to_site' => $request->boolean('published_to_site'),
            ]);
        } catch (RuntimeException $e) {
            return back()
                ->withInput()
                ->withErrors(['stripe' => $e->getMessage()]);
        }

        if ($service->published_to_site) {
            app(WordPressWebhookDispatcher::class)->servicesSync($tenant);
        }

        return redirect()
            ->route('admin.services.show', [$tenant, $service])
            ->with('status', 'Servizio aggiornato su Hub e Stripe.');
    }

    public function destroy(Tenant $tenant, PayableService $service): RedirectResponse
    {
        abort_unless($service->tenant_id === $tenant->id && $service->type === 'service', 404);

        $wasPublished = $service->published_to_site;

        if (TenantStripeConfig::isConfigured($tenant) && $service->stripe_payment_link_id) {
            try {
                (new StripePaymentLinkService(TenantStripeConfig::secretKey($tenant)))
                    ->deactivatePaymentLink($service->stripe_payment_link_id);
            } catch (RuntimeException) {
                // Il link può essere già disattivato manualmente su Stripe.
            }
        }

        if ($service->cover_image_path) {
            Storage::disk('public')->delete($service->cover_image_path);
        }

        $service->update(['status' => 'archived', 'published_to_site' => false]);

        if ($wasPublished) {
            app(WordPressWebhookDispatcher::class)->servicesSync($tenant);
        }

        return redirect()
            ->route('admin.services.index', $tenant)
            ->with('status', 'Servizio archiviato e link Stripe disattivato.');
    }

    public function refreshPaymentMethods(Tenant $tenant, PayableService $service): RedirectResponse
    {
        abort_unless($service->tenant_id === $tenant->id && $service->type === 'service', 404);

        if (! TenantStripeConfig::isConfigured($tenant) || ! $service->stripe_price_id || ! $service->stripe_payment_link_id) {
            return back()->withErrors(['stripe' => 'Servizio non collegato correttamente a Stripe.']);
        }

        try {
            $stripe = new StripePaymentLinkService(TenantStripeConfig::secretKey($tenant));
            $link = $stripe->replacePaymentLink($service->stripe_payment_link_id, $service->stripe_price_id);

            $service->update([
                'stripe_payment_link_id' => $link['id'],
                'payment_url' => $link['url'],
            ]);
        } catch (RuntimeException $e) {
            return back()->withErrors(['stripe' => $e->getMessage()]);
        }

        return back()->with('status', 'Nuovo link generato con i metodi di pagamento attivi su Stripe. Aggiorna il link inviato ai clienti.');
    }

    public function togglePublish(Tenant $tenant, PayableService $service): RedirectResponse
    {
        abort_unless($service->tenant_id === $tenant->id, 404);

        $service->update(['published_to_site' => ! $service->published_to_site]);

        app(WordPressWebhookDispatcher::class)->servicesSync($tenant);

        return back()->with('status', $service->published_to_site
            ? 'Servizio visibile su inm35.it e beautyofimage.com.'
            : 'Servizio nascosto dal sito.');
    }

    /** @return array{included: int, used: int, remaining: int, paid_price: int} */
    private function quotaFor(Tenant $tenant): array
    {
        return [
            'included' => TenantServiceQuota::includedLimit($tenant),
            'used' => TenantServiceQuota::usedCount($tenant),
            'remaining' => TenantServiceQuota::remaining($tenant),
            'paid_price' => TenantServiceQuota::paidUnlockPrice($tenant),
        ];
    }

    private function storeCoverImage(Request $request, Tenant $tenant): ?string
    {
        if (! $request->hasFile('cover_image')) {
            return null;
        }

        $file = $request->file('cover_image');
        $directory = 'services/'.$tenant->slug;

        try {
            return ImageOptimizer::toWebp($file->getRealPath(), $file->getMimeType(), $directory);
        } catch (\Throwable $e) {
            return $file->store($directory, 'public');
        }
    }
}
