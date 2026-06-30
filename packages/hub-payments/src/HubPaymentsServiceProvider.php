<?php

namespace M35\HubPayments;

use Illuminate\Support\Facades\Route;
use M35\HubPayments\Http\Controllers\Admin\ServiceController;
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
                Route::post('/services/{service}/publish', [ServiceController::class, 'togglePublish'])->name('services.publish');
            });

        Route::prefix('api/v1')
            ->name('api.')
            ->group(function () {
                Route::get('{tenantSlug}/services', [ServiceApiController::class, 'index'])->name('services.index');
            });
    }
}
