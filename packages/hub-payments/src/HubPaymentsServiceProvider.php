<?php

namespace M35\HubPayments;

use App\Models\Tenant;
use Illuminate\Support\Facades\Route;
use M35\HubPayments\Http\Controllers\Admin\ServiceController;
use M35\HubPayments\Http\Controllers\Admin\StripePaymentLinksController;
use M35\HubPayments\Http\Controllers\Api\ServiceApiController;
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
                Route::get('/services/{service}', [ServiceController::class, 'show'])->name('services.show');
                Route::get('/services/{service}/edit', [ServiceController::class, 'edit'])->name('services.edit');
                Route::put('/services/{service}', [ServiceController::class, 'update'])->name('services.update');
                Route::delete('/services/{service}', [ServiceController::class, 'destroy'])->name('services.destroy');
                Route::post('/services/{service}/publish', [ServiceController::class, 'togglePublish'])->name('services.publish');
                Route::post('/services/{service}/refresh-payment-methods', [ServiceController::class, 'refreshPaymentMethods'])->name('services.refresh-payment-methods');
                Route::get('/payment-links', [StripePaymentLinksController::class, 'index'])->name('services.payment-links');
                Route::post('/payment-links/{link}/deactivate', [StripePaymentLinksController::class, 'deactivate'])->name('services.payment-links.deactivate');
                Route::post('/payment-links/{link}/import', [StripePaymentLinksController::class, 'import'])->name('services.payment-links.import');
            });

        Route::prefix('api/v1')
            ->name('api.')
            ->group(function () {
                Route::get('{tenantSlug}/services', [ServiceApiController::class, 'index'])->name('services.index');
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
