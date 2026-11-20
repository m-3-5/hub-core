<?php

use App\Http\Controllers\Api\PromoApiController;
use App\Http\Controllers\Api\PromoDraftApiController;
use App\Http\Controllers\StripeBillingWebhookController;
use Illuminate\Support\Facades\Route;
use M35\HubPayments\Http\Middleware\VerifyHubSignature;

Route::prefix('v1')->name('api.')->group(function () {
    Route::get('{tenantSlug}/promos', [PromoApiController::class, 'index'])->name('promos.index');
    Route::get('{tenantSlug}/promos/{promoSlug}', [PromoApiController::class, 'show'])->name('promos.show');

    // Bozze create da un post esterno (Facebook…): chiamata firmata (HMAC con HUB_BRIDGE_SECRET), come checkout e preventivi.
    Route::post('{tenantSlug}/promos/drafts', [PromoDraftApiController::class, 'store'])
        ->middleware([VerifyHubSignature::class, 'throttle:60,1'])
        ->name('promos.drafts.store');
});

// Annunci online per paese, letti da piramide35.com (cache 5 minuti, CORS per piramide35.com).
Route::get('annunci', [\App\Http\Controllers\Api\ClassifiedFeedController::class, 'index'])->middleware('throttle:120,1')->name('api.classifieds.feed');

// Avvisi di Meta sui nuovi post delle pagine collegate (verifica + post firmati).
Route::get('facebook/webhook', [\App\Http\Controllers\FacebookController::class, 'verify'])->name('facebook.webhook.verify');
Route::post('facebook/webhook', [\App\Http\Controllers\FacebookController::class, 'webhook'])->middleware('throttle:300,1')->name('facebook.webhook');

Route::post('stripe/webhook', [StripeBillingWebhookController::class, 'handle'])->name('stripe.webhook');
