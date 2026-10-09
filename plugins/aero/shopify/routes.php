<?php

use Aero\Shopify\Http\Controllers\PayPageController;
use Aero\Shopify\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::post('api/v1/shopify/webhooks/{uuid}', [WebhookController::class, 'handle']);

Route::prefix('shopify/pagar')->group(function () {
    Route::get('o/{token}', [PayPageController::class, 'show']);
    Route::get('o/{token}/estado', [PayPageController::class, 'status'])->middleware('throttle:60,1');
    Route::get('{uuid}', [PayPageController::class, 'lookupForm']);
    Route::post('{uuid}', [PayPageController::class, 'lookup'])->middleware('throttle:10,1');
});
