<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\AppController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\MaxController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\BillingController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ModuleBillingController;
use App\Http\Controllers\Admin\PromoController;
use App\Http\Controllers\Admin\PromoPreviewController;
use App\Http\Controllers\Admin\ClassifiedAdController;
use App\Http\Controllers\Admin\CustomerTicketController;
use App\Http\Controllers\ClassifiedPublicController;
use App\Http\Controllers\MagicLoginController;
use App\Http\Controllers\Admin\SiteLeadController;
use App\Http\Controllers\Admin\TicketController;
use App\Http\Controllers\Admin\WizardController;
use App\Http\Controllers\Auth\WordPressBridgeController;
use App\Http\Controllers\ClientSiteController;
use App\Http\Controllers\EmbedController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\GuestController;
use App\Http\Controllers\HubPromoArchiveController;
use App\Http\Controllers\PricingController;
use App\Http\Controllers\PromoArchiveController;
use App\Http\Controllers\PromoPublicController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\LlmsController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\WelcomeController;
use App\Models\Tenant;
use Illuminate\Support\Facades\Route;

Route::get('/', WelcomeController::class)->name('welcome');

Route::get('/prezzi', [PricingController::class, 'show'])->name('pricing.show');

// Landing «Siti web e app a Corigliano-Rossano» + elenco pagine per Google.
Route::get('/siti-web-corigliano-rossano', [LandingController::class, 'show'])->name('landing.web');
Route::post('/siti-web-corigliano-rossano/richiesta', [LandingController::class, 'store'])
    ->middleware('throttle:6,10')
    ->name('landing.web.lead');
Route::post('/siti-web-corigliano-rossano/parti', [LandingController::class, 'start'])
    ->middleware('throttle:6,10')
    ->name('landing.web.start');
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/llms.txt', LlmsController::class)->name('llms');
Route::get('/indexnow-key.txt', fn () => response(\App\Console\Commands\IndexNowSubmit::key(), 200, ['Content-Type' => 'text/plain; charset=UTF-8']))->name('indexnow.key');

// Guide su come ricevere i soldi delle vendite + condizioni economiche (pagine pubbliche).
Route::get('/guide/pagamenti', [\App\Http\Controllers\GuideController::class, 'index'])->name('guides.index');
Route::get('/guide/pagamenti/{slug}', [\App\Http\Controllers\GuideController::class, 'show'])->name('guides.show');
Route::get('/condizioni-economiche', [\App\Http\Controllers\GuideController::class, 'economicTerms'])->name('terms.economic');

Route::post('/max/chat', [\App\Http\Controllers\MaxChatController::class, 'ask'])
    ->middleware('throttle:20,1')
    ->name('max.chat');

Route::get('/registrati', [RegistrationController::class, 'create'])->name('registration.create');
Route::post('/registrati', [RegistrationController::class, 'store'])
    ->middleware('throttle:5,10')
    ->name('registration.store');

Route::get('/registrati/conferma/{token}', [RegistrationController::class, 'confirm'])
    ->name('registration.confirm');

Route::post('/ospite/inizia', [GuestController::class, 'start'])
    ->middleware('throttle:5,10')
    ->name('guest.start');

Route::get('/ospite/conferma/{token}', [GuestController::class, 'confirmPublish'])
    ->name('guest.confirm-publish');

Route::get('/auth/wp-bridge', WordPressBridgeController::class)->name('auth.wp-bridge');

Route::middleware('signed')->group(function () {
    Route::get('/feedback/{tenant}', [FeedbackController::class, 'show'])->name('feedback.show');
    Route::post('/feedback/{tenant}', [FeedbackController::class, 'store'])
        ->middleware('throttle:10,10')
        ->name('feedback.store');
});

Route::get('/promo', HubPromoArchiveController::class)->name('promo.hub-archive');

Route::get('/p/{tenant}', PromoArchiveController::class)->name('promo.archive');

Route::get('/p/{tenant}/{promo}', [PromoPublicController::class, 'show'])
    ->name('promo.show')
    ->scopeBindings();

Route::post('/p/{tenant}/{promo}/contatto', [PromoPublicController::class, 'contact'])
    ->name('promo.contact')
    ->middleware('throttle:5,10')
    ->scopeBindings();

Route::get('/accesso/{user}/{tenant}', [MagicLoginController::class, 'login'])
    ->middleware(['signed', 'throttle:20,1'])
    ->name('magic.login');

Route::get('/annunci',[ClassifiedPublicController::class, 'board'])->name('classifieds.board');
Route::get('/a/{tenant}', [ClassifiedPublicController::class, 'board'])->name('classifieds.tenant-board');
Route::get('/a/{tenant}/{classifiedAd}', [ClassifiedPublicController::class, 'show'])
    ->name('classifieds.show')
    ->scopeBindings();
Route::post('/a/{tenant}/{classifiedAd}/contatto', [ClassifiedPublicController::class, 'contact'])
    ->name('classifieds.contact')
    ->middleware('throttle:5,10')
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
        Route::delete('/tenants/{tenant}', [DashboardController::class, 'destroy'])->name('tenants.destroy');

        // Creazione guidata a schermo intero (promo, prodotti, servizi).
        Route::get('/tenants/{tenant}/new/{kind}', [WizardController::class, 'show'])->whereIn('kind', ['promo', 'product', 'service'])->name('wizard.show');
        Route::post('/tenants/{tenant}/wizard/suggest', [WizardController::class, 'suggest'])->middleware('throttle:20,1')->name('wizard.suggest');

        Route::get('/tenants/{tenant}/promos', [PromoController::class, 'index'])->name('promos.index');
        Route::get('/tenants/{tenant}/promos/create', [PromoController::class, 'create'])->name('promos.create');
        Route::post('/tenants/{tenant}/promos', [PromoController::class, 'store'])->name('promos.store');
        Route::get('/tenants/{tenant}/promos/{promo}', [PromoController::class, 'show'])->name('promos.show');
        Route::get('/tenants/{tenant}/promos/{promo}/preview', [PromoPreviewController::class, 'show'])->name('promos.preview');
        Route::get('/tenants/{tenant}/promos/{promo}/edit', [PromoController::class, 'edit'])->name('promos.edit');
        Route::put('/tenants/{tenant}/promos/{promo}', [PromoController::class, 'update'])->name('promos.update');
        Route::post('/tenants/{tenant}/promos/{promo}/publish', [PromoController::class, 'publish'])->name('promos.publish');
        Route::post('/tenants/{tenant}/promos/{promo}/video', [PromoController::class, 'generateVideo'])->name('promos.generate-video');
        Route::post('/tenants/{tenant}/promos/{promo}/image-pack', [PromoController::class, 'generateImagePack'])->name('promos.generate-image-pack');
        Route::delete('/tenants/{tenant}/promos/{promo}', [PromoController::class, 'destroy'])->name('promos.destroy');

        Route::get('/tenants/{tenant}/billing', [BillingController::class, 'show'])->name('billing.show');
        Route::post('/tenants/{tenant}/billing/checkout', [BillingController::class, 'checkout'])->name('billing.checkout');
        Route::post('/tenants/{tenant}/billing/launch', [BillingController::class, 'launchCheckout'])->name('billing.launch');
        Route::post('/tenants/{tenant}/billing/modules/{module}/toggle', [BillingController::class, 'toggleModule'])->name('billing.modules.toggle');

        Route::get('/tenants/{tenant}/module-billing', [ModuleBillingController::class, 'show'])->name('module-billing.show');
        Route::post('/tenants/{tenant}/module-billing', [ModuleBillingController::class, 'store'])->name('module-billing.store');
        Route::post('/tenants/{tenant}/module-billing/{charge}/toggle-paid', [ModuleBillingController::class, 'togglePaid'])->name('module-billing.toggle-paid');
        Route::delete('/tenants/{tenant}/module-billing/{charge}', [ModuleBillingController::class, 'destroy'])->name('module-billing.destroy');

        Route::post('/tenants/{tenant}/tickets', [TicketController::class, 'store'])->name('tickets.store');
        Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index');
        Route::post('/tickets/{ticket}/respond', [TicketController::class, 'respond'])->name('tickets.respond');

        Route::get('/tenants/{tenant}/classifieds', [ClassifiedAdController::class, 'index'])->name('classifieds.index');
        Route::get('/tenants/{tenant}/classifieds/create', [ClassifiedAdController::class, 'create'])->name('classifieds.create');
        Route::post('/tenants/{tenant}/classifieds', [ClassifiedAdController::class, 'store'])->name('classifieds.store');
        Route::get('/tenants/{tenant}/classifieds/{classifiedAd}/edit', [ClassifiedAdController::class, 'edit'])->name('classifieds.edit');
        Route::put('/tenants/{tenant}/classifieds/{classifiedAd}', [ClassifiedAdController::class, 'update'])->name('classifieds.update');
        Route::post('/tenants/{tenant}/classifieds/{classifiedAd}/publish', [ClassifiedAdController::class, 'publish'])->name('classifieds.publish');
        Route::post('/tenants/{tenant}/classifieds/{classifiedAd}/unpublish', [ClassifiedAdController::class, 'unpublish'])->name('classifieds.unpublish');
        Route::delete('/tenants/{tenant}/classifieds/{classifiedAd}', [ClassifiedAdController::class, 'destroy'])->name('classifieds.destroy');

        Route::get('/tenants/{tenant}/customer-tickets', [CustomerTicketController::class, 'index'])->name('customer-tickets.index');
        Route::post('/tenants/{tenant}/customer-tickets/{customerTicket}/toggle-status', [CustomerTicketController::class, 'toggleStatus'])->name('customer-tickets.toggle-status');

        Route::get('/leads', [SiteLeadController::class, 'index'])->name('leads.index');
        Route::post('/leads/{lead}/toggle', [SiteLeadController::class, 'toggle'])->name('leads.toggle');

        Route::get('/activity', [ActivityLogController::class, 'index'])->name('activity.index');

        Route::get('/feedback', [\App\Http\Controllers\Admin\FeedbackAdminController::class, 'index'])->name('feedback.index');

        Route::post('/tenants/{tenant}/max/query', [MaxController::class, 'query'])->name('max.query');

        Route::get('/tenants/{tenant}/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/tenants/{tenant}/profile', [ProfileController::class, 'update'])->name('profile.update');
    });
});

Route::middleware(['auth', 'tenant.access'])->prefix('app')->name('app.')->group(function () {
    Route::get('/', [AppController::class, 'index'])->name('index');
    Route::get('/{tenant}', [AppController::class, 'home'])->name('home');
});

Route::bind('tenant', fn (string $value) => Tenant::where('slug', $value)->firstOrFail());
