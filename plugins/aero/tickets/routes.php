<?php

use Aero\Tickets\Http\Controllers\PublicController;
use Illuminate\Support\Facades\Route;

// Páginas públicas del tenant del host (catálogo, evento, orden, entrada).
// Escáner de puerta: se autentica con el secreto del enlace (no con sesión), por eso las lecturas no usan CSRF.
Route::middleware(['web', 'throttle:240,1'])->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->prefix('escaner/{token}')->group(function () {
        Route::get('/', [\Aero\Tickets\Http\Controllers\ScannerController::class, 'page']);
        Route::post('validar', [\Aero\Tickets\Http\Controllers\ScannerController::class, 'validateScan']);
        Route::get('buscar', [\Aero\Tickets\Http\Controllers\ScannerController::class, 'search']);
        Route::get('estado', [\Aero\Tickets\Http\Controllers\ScannerController::class, 'stats']);
    });

Route::middleware(['web'])->group(function () {
    Route::get('eventos', [PublicController::class, 'catalog']);
    Route::get('evento/{slug}', [PublicController::class, 'show']);
    Route::post('evento/{slug}/comprar', [PublicController::class, 'buy'])->middleware('throttle:10,1');
    Route::get('orden/{code}', [PublicController::class, 'order']);
    Route::post('orden/{code}/pagar', [PublicController::class, 'pay'])->middleware('throttle:10,1');
    Route::post('orden/{code}/informar', [PublicController::class, 'inform'])->middleware('throttle:10,1');
    Route::post('orden/{code}/cancelar', [PublicController::class, 'cancelOrder'])->middleware('throttle:10,1');
    Route::get('pedido/{code}', fn (string $code) => redirect('orden/' . $code));
    Route::get('entrada/{token}', [PublicController::class, 'ticket']);
    Route::get('entrada/{token}/pdf', [PublicController::class, 'ticketPdf'])->middleware('throttle:30,1');
    Route::get('orden/{code}/entradas.pdf', [PublicController::class, 'orderPdf'])->middleware('throttle:30,1');
    Route::post('orden/{code}/reenviar', [PublicController::class, 'resend'])->middleware('throttle:5,10');
    Route::get('mis-entradas', [PublicController::class, 'myTickets']);
    Route::post('mis-entradas', [PublicController::class, 'myTicketsSend'])->middleware('throttle:5,1');
    Route::get('mis-entradas/ver', [PublicController::class, 'myTicketsView'])->middleware('throttle:30,1');
});
