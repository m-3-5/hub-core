<?php

namespace M35\HubPayments;

use App\Models\Tenant;
use Illuminate\Support\Facades\Route;
use M35\HubPayments\Http\Controllers\Admin\ServiceController;
use M35\HubPayments\Http\Controllers\Admin\StripeCatalogController;
use M35\HubPayments\Http\Controllers\Api\ServiceApiController;
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
        Route::middleware(['web', 'auth', 'tenant.access'])
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
                Route::get('/stripe-catalog', [StripeCatalogController::class, 'index'])->name('services.stripe-catalog');
                Route::post('/stripe-catalog/{product}/archive', [StripeCatalogController::class, 'archive'])->name('services.stripe-catalog.archive');
            });

        Route::prefix('api/v1')
            ->name('api.')
            ->group(function () {
                Route::get('{tenantSlug}/services', [ServiceApiController::class, 'index'])->name('services.index');
            });
    }
}
