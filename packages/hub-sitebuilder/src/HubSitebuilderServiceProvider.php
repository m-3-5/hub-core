<?php

namespace M35\HubSitebuilder;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use M35\HubSitebuilder\Http\Controllers\Admin\SiteBuilderController;
use M35\HubSitebuilder\Http\Controllers\Public\SiteController;

class HubSitebuilderServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'hub-sitebuilder');

        $this->registerRoutes();
    }

    private function registerRoutes(): void
    {
        Route::middleware(['web', 'auth', 'tenant.access'])
            ->prefix('admin/tenants/{tenant}')
            ->name('admin.')
            ->group(function () {
                Route::get('/sitebuilder', [SiteBuilderController::class, 'show'])->name('sitebuilder.show');
                Route::post('/sitebuilder', [SiteBuilderController::class, 'generate'])->name('sitebuilder.generate');
            });

        Route::middleware('web')
            ->get('/site/{tenant}', [SiteController::class, 'show'])
            ->name('site.public.show');
    }
}
