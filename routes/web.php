<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PromoController;
use App\Http\Controllers\ClientSiteController;
use App\Http\Controllers\EmbedController;
use App\Http\Controllers\PromoPublicController;
use App\Models\Tenant;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('admin.login');
});

Route::get('/p/{tenant}/{promo}', [PromoPublicController::class, 'show'])
    ->name('promo.show')
    ->scopeBindings();
// Senza .js: su Plesk/nginx molte URL *.js non arrivano a Laravel (404).
Route::get('/embed/{tenantSlug}', [EmbedController::class, 'script'])->name('embed.script');
Route::get('/embed/{tenantSlug}.js', [EmbedController::class, 'script']);

Route::get('/client/{tenant}/{promo}/embed', [ClientSiteController::class, 'embedPage'])
    ->name('client.promo.embed')
    ->scopeBindings();
Route::get('/client/{tenant}/{promo}/iframe-snippet', [ClientSiteController::class, 'iframeSnippet'])
    ->name('client.promo.iframe')
    ->scopeBindings();

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.submit');

    Route::middleware('admin')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('/tenants/{tenant}/promos/create', [PromoController::class, 'create'])->name('promos.create');
        Route::post('/tenants/{tenant}/promos', [PromoController::class, 'store'])->name('promos.store');
        Route::get('/tenants/{tenant}/promos/{promo}', [PromoController::class, 'show'])->name('promos.show');
    });
});

Route::bind('tenant', fn (string $value) => Tenant::where('slug', $value)->firstOrFail());
