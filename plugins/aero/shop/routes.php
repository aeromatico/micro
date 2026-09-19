<?php

use Aero\Shop\Http\Controllers\Api\OrdersController;
use Aero\Shop\Http\Controllers\Api\ProductsController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/shop')->group(function () {
    Route::middleware('aero.api:shop.products.read')->group(function () {
        Route::get('products', [ProductsController::class, 'index']);
        Route::get('products/{id}', [ProductsController::class, 'show']);
        Route::get('payment-methods', [ProductsController::class, 'paymentMethods']);
    });

    Route::middleware('aero.api:shop.orders.read')->group(function () {
        Route::get('orders', [OrdersController::class, 'index']);
        Route::get('orders/{ref}', [OrdersController::class, 'show']);
    });

    Route::middleware('aero.api:shop.orders.write')->group(function () {
        Route::post('orders', [OrdersController::class, 'store']);
        Route::post('orders/{ref}/cancel', [OrdersController::class, 'cancel']);
    });
});
