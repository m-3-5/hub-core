<?php

use App\Http\Controllers\Admin\AppController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PromoController;
use App\Http\Controllers\Admin\PromoPreviewController;
use App\Http\Controllers\Auth\WordPressBridgeController;
use App\Http\Controllers\ClientSiteController;
use App\Http\Controllers\EmbedController;
use App\Http\Controllers\PromoArchiveController;
use App\Http\Controllers\PromoPublicController;
use App\Http\Controllers\WelcomeController;
use App\Models\Tenant;
use Illuminate\Support\Facades\Route;

Route::get('/', WelcomeController::class)->name('welcome');

Route::get('/auth/wp-bridge', WordPressBridgeController::class)->name('auth.wp-bridge');

Route::get('/p/{tenant}', PromoArchiveController::class)->name('promo.archive');

Route::get('/p/{tenant}/{promo}', [PromoPublicController::class, 'show'])
    ->name('promo.show')
    ->scopeBindings();

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

    Route::get('/password/dimenticata', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/password/email', [AuthController::class, 'sendResetLink'])->name('password.email');
    Route::get('/password/reimposta/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/password/reimposta', [AuthController::class, 'resetPassword'])->name('password.update');

    Route::middleware(['auth', 'tenant.access'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('/tenants/{tenant}/promos', [PromoController::class, 'index'])->name('promos.index');
        Route::get('/tenants/{tenant}/promos/create', [PromoController::class, 'create'])->name('promos.create');
        Route::post('/tenants/{tenant}/promos', [PromoController::class, 'store'])->name('promos.store');
        Route::get('/tenants/{tenant}/promos/{promo}', [PromoController::class, 'show'])->name('promos.show');
        Route::get('/tenants/{tenant}/promos/{promo}/preview', [PromoPreviewController::class, 'show'])->name('promos.preview');
        Route::get('/tenants/{tenant}/promos/{promo}/edit', [PromoController::class, 'edit'])->name('promos.edit');
        Route::put('/tenants/{tenant}/promos/{promo}', [PromoController::class, 'update'])->name('promos.update');
        Route::post('/tenants/{tenant}/promos/{promo}/publish', [PromoController::class, 'publish'])->name('promos.publish');
        Route::delete('/tenants/{tenant}/promos/{promo}', [PromoController::class, 'destroy'])->name('promos.destroy');
    });
});

Route::middleware(['auth', 'tenant.access'])->prefix('app')->name('app.')->group(function () {
    Route::get('/', [AppController::class, 'index'])->name('index');
    Route::get('/{tenant}', [AppController::class, 'home'])->name('home');
});

Route::bind('tenant', fn (string $value) => Tenant::where('slug', $value)->firstOrFail());
