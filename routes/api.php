<?php

use App\Http\Controllers\Api\PromoApiController;
use App\Http\Controllers\StripeBillingWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.')->group(function () {
    Route::get('{tenantSlug}/promos', [PromoApiController::class, 'index'])->name('promos.index');
    Route::get('{tenantSlug}/promos/{promoSlug}', [PromoApiController::class, 'show'])->name('promos.show');
});

Route::post('stripe/webhook', [StripeBillingWebhookController::class, 'handle'])->name('stripe.webhook');
