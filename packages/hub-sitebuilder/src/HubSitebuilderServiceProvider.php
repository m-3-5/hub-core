<?php

namespace M35\HubSitebuilder;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use M35\HubSitebuilder\Http\Controllers\Admin\SiteArchiveController;
use M35\HubSitebuilder\Http\Controllers\Admin\SiteBuilderController;
use M35\HubSitebuilder\Http\Controllers\Public\SiteController;
use M35\HubSitebuilder\Http\Controllers\Public\SiteEditController;

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
                Route::post('/sitebuilder', [SiteBuilderController::class, 'generate'])->middleware('throttle:10,1')->name('sitebuilder.generate');
                Route::post('/sitebuilder/link', [SiteBuilderController::class, 'editLink'])->middleware('throttle:5,10')->name('sitebuilder.edit-link');
            });

        // Archivio dei siti rimossi (solo amministratore, controllato nel controller).
        Route::middleware(['web', 'auth'])
            ->prefix('admin')
            ->name('admin.')
            ->group(function () {
                Route::get('/site-archive', [SiteArchiveController::class, 'index'])->name('site-archive.index');
                Route::delete('/site-archive/{archivedSite}', [SiteArchiveController::class, 'destroy'])->name('site-archive.destroy');
            });

        Route::middleware('web')
            ->get('/site/{tenant}', [SiteController::class, 'show'])
            ->name('site.public.show');

        // Modifica con il link segreto arrivato per email (nessun account).
        Route::middleware(['web', 'throttle:60,1'])->group(function () {
            Route::get('/sito/modifica/{token}', [SiteEditController::class, 'show'])->name('site.edit.show');
            Route::post('/sito/modifica/{token}', [SiteEditController::class, 'update'])->middleware('throttle:15,1')->name('site.edit.update');
        });
    }
}
