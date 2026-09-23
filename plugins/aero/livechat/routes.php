<?php

use Aero\Livechat\Http\Controllers\WidgetController;
use Aero\Livechat\Http\Middleware\Cors;
use Aero\Livechat\Http\Middleware\ForceJson;
use Aero\Livechat\Http\Middleware\ThrottleJson;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/livechat')->middleware(['api', ForceJson::class, Cors::class])->group(function () {
    // Preflight del navegador antes de cada POST/GET cross-origin: sin esta
    // ruta explícita, Laravel devuelve 404 antes de llegar al middleware Cors.
    Route::options('{any}', fn () => response('', 204))->where('any', '.*');

    // ThrottleJson en vez de `throttle`: ver su docblock — el `throttle` de
    // Laravel corta con una excepción que se salta el middleware Cors por
    // prioridad fija del Kernel, y esa respuesta 429 sin headers de CORS
    // queda bloqueada por el navegador (el widget la veía como error de red).
    Route::post('start', [WidgetController::class, 'start'])->middleware(ThrottleJson::class . ':60,1');
    Route::post('message', [WidgetController::class, 'message'])->middleware(ThrottleJson::class . ':60,1');
    Route::get('messages', [WidgetController::class, 'messages'])->middleware(ThrottleJson::class . ':120,1');
    Route::get('unread', [WidgetController::class, 'unread'])->middleware(ThrottleJson::class . ':60,1');
    Route::post('attachment', [WidgetController::class, 'attachment'])->middleware(ThrottleJson::class . ':20,1');
    Route::get('attachments/{token}', [WidgetController::class, 'attachmentDownload'])->middleware(ThrottleJson::class . ':120,1')->where('token', '[A-Za-z0-9]{40}');
    Route::post('transcript', [WidgetController::class, 'transcript'])->middleware(ThrottleJson::class . ':10,1');
    Route::post('end', [WidgetController::class, 'end'])->middleware(ThrottleJson::class . ':20,1');
});
