<?php

namespace M35\HubPayments;

use App\Models\Tenant;
use Illuminate\Support\Facades\Route;
use M35\HubPayments\Http\Controllers\Admin\ServiceController;
use M35\HubPayments\Http\Controllers\Admin\CommissionAdminController;
use M35\HubPayments\Http\Controllers\Admin\ConnectController;
use M35\HubPayments\Http\Controllers\Admin\OrderController;
use M35\HubPayments\Http\Controllers\Admin\QuoteController;
use M35\HubPayments\Http\Controllers\Admin\StripePaymentLinksController;
use M35\HubPayments\Http\Controllers\Admin\StripeWebhookSettingsController;
use M35\HubPayments\Http\Controllers\Api\CheckoutApiController;
use M35\HubPayments\Http\Controllers\Api\QuoteApiController;
use M35\HubPayments\Http\Controllers\Api\ServiceApiController;
use M35\HubPayments\Http\Controllers\Api\TenantStripeWebhookController;
use M35\HubPayments\Http\Middleware\VerifyHubSignature;
use M35\HubPayments\Http\Controllers\Public\ServicePublicController;
use M35\HubPayments\Models\PayableService;
use Illuminate\Support\ServiceProvider;

class HubPaymentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/hub-payments.php', 'hub-payments');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'hub-payments');

        $this->publishes([
            __DIR__.'/../config/hub-payments.php' => config_path('hub-payments.php'),
        ], 'hub-payments-config');

        $this->registerRoutes();
        $this->registerRouteBindings();
    }

    private function registerRouteBindings(): void
    {
        Route::bind('service', function (string $value, $route) {
            $tenant = $route->parameter('tenant');

            if (! $tenant instanceof Tenant) {
                $tenant = Tenant::where('slug', $tenant)->firstOrFail();
            }

            return PayableService::query()
                ->where('tenant_id', $tenant->id)
                ->where('slug', $value)
                ->firstOrFail();
        });
    }

    private function registerRoutes(): void
    {
        Route::middleware(['web', 'auth', 'tenant.access', 'tenant.module:services'])
            ->prefix('admin/tenants/{tenant}')
            ->name('admin.')
            ->group(function () {
                Route::get('/services', [ServiceController::class, 'index'])->name('services.index');
                Route::get('/services/create', [ServiceController::class, 'create'])->name('services.create');
                Route::post('/services', [ServiceController::class, 'store'])->name('services.store');
                Route::post('/services/stripe-settings', [ServiceController::class, 'storeStripeSettings'])->name('services.stripe-settings');
                Route::post('/services/stripe-webhook', [StripeWebhookSettingsController::class, 'create'])->name('services.stripe-webhook.create');
                Route::post('/services/stripe-webhook/secret', [StripeWebhookSettingsController::class, 'store'])->name('services.stripe-webhook.secret');
                Route::get('/services/{service}', [ServiceController::class, 'show'])->name('services.show');
                Route::get('/services/{service}/edit', [ServiceController::class, 'edit'])->name('services.edit');
                Route::put('/services/{service}', [ServiceController::class, 'update'])->name('services.update');
                Route::delete('/services/{service}', [ServiceController::class, 'destroy'])->name('services.destroy');
                Route::post('/services/{service}/publish', [ServiceController::class, 'togglePublish'])->name('services.publish');
                Route::post('/services/{service}/refresh-payment-methods', [ServiceController::class, 'refreshPaymentMethods'])->name('services.refresh-payment-methods');
                Route::get('/payment-links', [StripePaymentLinksController::class, 'index'])->name('services.payment-links');
                Route::post('/payment-links/{link}/deactivate', [StripePaymentLinksController::class, 'deactivate'])->name('services.payment-links.deactivate');
                Route::post('/payment-links/{link}/import', [StripePaymentLinksController::class, 'import'])->name('services.payment-links.import');

                // Pagamenti protetti (Stripe Connect): il venditore si collega per ricevere i soldi delle vendite sul canale hub.
                Route::post('/connect/start', [ConnectController::class, 'start'])->name('connect.start');
                Route::get('/connect/refresh', [ConnectController::class, 'refresh'])->name('connect.refresh');
                Route::get('/connect/return', [ConnectController::class, 'return'])->name('connect.return');
                Route::post('/connect/sync', [ConnectController::class, 'sync'])->name('connect.sync');
                Route::post('/connect/dashboard', [ConnectController::class, 'dashboard'])->name('connect.dashboard');

                Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');

                // Preventivi a importo libero (type "quote"): fuori da quota servizi e addebiti modulo.
                Route::get('/quotes', [QuoteController::class, 'index'])->name('quotes.index');
                Route::post('/quotes', [QuoteController::class, 'store'])->name('quotes.store');
                Route::delete('/quotes/{service}', [QuoteController::class, 'destroy'])->name('quotes.destroy');

                // Prodotti: stesso flusso dei servizi, tipo "product" (il default 'kind' lo legge il controller).
                Route::prefix('products')->name('products.')->group(function () {
                    $routes = [
                        Route::get('/', [ServiceController::class, 'index'])->name('index'),
                        Route::get('/create', [ServiceController::class, 'create'])->name('create'),
                        Route::post('/', [ServiceController::class, 'store'])->name('store'),
                        Route::get('/{service}', [ServiceController::class, 'show'])->name('show'),
                        Route::get('/{service}/edit', [ServiceController::class, 'edit'])->name('edit'),
                        Route::put('/{service}', [ServiceController::class, 'update'])->name('update'),
                        Route::delete('/{service}', [ServiceController::class, 'destroy'])->name('destroy'),
                        Route::post('/{service}/publish', [ServiceController::class, 'togglePublish'])->name('publish'),
                        Route::post('/{service}/refresh-payment-methods', [ServiceController::class, 'refreshPaymentMethods'])->name('refresh-payment-methods'),
                    ];

                    foreach ($routes as $route) {
                        $route->defaults('kind', 'product');
                    }
                });
            });

        // Commissioni sul canale hub: solo super admin (controllo nel controller).
        Route::middleware(['web', 'auth'])->prefix('admin')->name('admin.')->group(function () {
            Route::get('/commissions', [CommissionAdminController::class, 'index'])->name('commissions.index');
            Route::put('/commissions/{tenant}', [CommissionAdminController::class, 'update'])->name('commissions.update');
        });

        Route::prefix('api/v1')
            ->name('api.')
            ->group(function () {
                Route::get('{tenantSlug}/services', [ServiceApiController::class, 'index'])->name('services.index');
                Route::get('{tenantSlug}/products', [ServiceApiController::class, 'products'])->name('products.index');

                // Chiamate riservate firmate dal sito del cliente (HMAC con HUB_BRIDGE_SECRET).
                Route::post('{tenantSlug}/checkout', [CheckoutApiController::class, 'store'])
                    ->middleware([VerifyHubSignature::class, 'throttle:30,1'])
                    ->name('checkout.store');

                Route::middleware([VerifyHubSignature::class, 'throttle:60,1'])->group(function () {
                    Route::post('{tenantSlug}/quotes', [QuoteApiController::class, 'store'])->name('quotes.store');
                    Route::get('{tenantSlug}/quotes', [QuoteApiController::class, 'index'])->name('quotes.index');
                    Route::get('{tenantSlug}/quotes/{quoteId}', [QuoteApiController::class, 'show'])->whereNumber('quoteId')->name('quotes.show');
                });

                // Webhook del conto Stripe del tenant (firma Stripe-Signature con segreto per tenant).
                Route::post('{tenantSlug}/stripe-webhook', [TenantStripeWebhookController::class, 'handle'])
                    ->middleware('throttle:120,1')
                    ->name('stripe.tenant-webhook');
            });

        Route::middleware('web')
            ->group(function () {
                Route::get('/s/{tenant}', [ServicePublicController::class, 'archive'])->name('services.public.archive');
                Route::get('/s/{tenant}/{service}', [ServicePublicController::class, 'show'])->name('services.public.show');
                Route::get('/client/{tenant}/services/{service}/embed', [ServicePublicController::class, 'embed'])->name('client.services.embed');
                Route::get('/client/{tenant}/services/{service}/iframe-snippet', [ServicePublicController::class, 'iframeSnippet'])->name('client.services.iframe');
            });
    }
}
