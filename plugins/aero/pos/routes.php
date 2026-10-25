<?php

use Aero\Pos\Http\Controllers\AuthController;
use Aero\Pos\Http\Controllers\OrdersController;
use Aero\Pos\Http\Controllers\PosController;
use Aero\Pos\Http\Middleware\AuthenticatePosToken;
use Aero\Pos\Http\Middleware\ForceJson;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/pos')->middleware(['api', ForceJson::class])->group(function () {
    // Público: nombre del negocio y acceso del cajero (PIN).
    Route::get('tenants/{handle}', [AuthController::class, 'tenant'])->middleware('throttle:30,1');
    Route::post('tenants/{handle}/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware(AuthenticatePosToken::class)->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [PosController::class, 'me']);
        Route::get('catalog', [PosController::class, 'catalog']);
        Route::post('shift/open', [PosController::class, 'shiftOpen']);
        Route::post('shift/close', [PosController::class, 'shiftClose']);
        Route::post('shift/movement', [PosController::class, 'movement']);
        Route::post('qr', [PosController::class, 'qrIssue'])->middleware('throttle:30,1');
        Route::get('qr/{reference}', [PosController::class, 'qrStatus'])->middleware('throttle:60,1');
        Route::get('orders', [OrdersController::class, 'index']);
        Route::get('orders/{id}', [OrdersController::class, 'show'])->whereNumber('id');
        Route::post('orders/{id}/verify', [OrdersController::class, 'verify'])->whereNumber('id')->middleware('throttle:60,1');
        Route::post('orders/{id}/collect', [OrdersController::class, 'collect'])->whereNumber('id');
        Route::post('sales', [PosController::class, 'sale']);
        Route::post('sales/{id}/receipt/whatsapp', [PosController::class, 'receiptWhatsapp'])->whereNumber('id')->middleware('throttle:20,1');
        Route::get('sales/{id}/receipt', [PosController::class, 'receipt'])->whereNumber('id');
    });
});
