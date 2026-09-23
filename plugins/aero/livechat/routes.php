<?php

use Aero\Livechat\Http\Controllers\WidgetController;
use Aero\Livechat\Http\Middleware\Cors;
use Aero\Livechat\Http\Middleware\ForceJson;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/livechat')->middleware(['api', ForceJson::class, Cors::class])->group(function () {
    // Preflight del navegador antes de cada POST/GET cross-origin: sin esta
    // ruta explícita, Laravel devuelve 404 antes de llegar al middleware Cors.
    Route::options('{any}', fn () => response('', 204))->where('any', '.*');

    Route::post('start', [WidgetController::class, 'start'])->middleware('throttle:60,1');
    Route::post('message', [WidgetController::class, 'message'])->middleware('throttle:60,1');
    Route::get('messages', [WidgetController::class, 'messages'])->middleware('throttle:120,1');
    Route::get('unread', [WidgetController::class, 'unread'])->middleware('throttle:60,1');
});
