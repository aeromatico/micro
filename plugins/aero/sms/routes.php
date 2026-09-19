<?php

use Aero\Sms\Http\Controllers\Api\BatchesController;
use Aero\Sms\Http\Controllers\Api\MessagesController;
use Aero\Sms\Http\Controllers\Api\UsageController;
use Aero\Sms\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/sms')->group(function () {
    Route::post('webhooks/twilio', [WebhookController::class, 'twilio']);
    Route::post('webhooks/twilio/inbound', [WebhookController::class, 'twilioInbound']);

    Route::middleware('aero.api:sms.send')->group(function () {
        Route::post('messages', [MessagesController::class, 'store']);
        Route::post('messages/{uuid}/cancel', [MessagesController::class, 'cancel']);
        Route::post('batches', [BatchesController::class, 'store']);
        Route::post('batches/{uuid}/cancel', [BatchesController::class, 'cancel']);
        Route::post('quote', [UsageController::class, 'quote']);
    });

    Route::middleware('aero.api:sms.read')->group(function () {
        Route::get('messages', [MessagesController::class, 'index']);
        Route::get('messages/{uuid}', [MessagesController::class, 'show']);
        Route::get('batches/{uuid}', [BatchesController::class, 'show']);
        Route::get('batches/{uuid}/messages', [BatchesController::class, 'messages']);
        Route::get('usage', [UsageController::class, 'index']);
    });
});
