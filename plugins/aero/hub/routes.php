<?php

use Aero\Hub\Http\Controllers\Api\ProxyController;
use Illuminate\Support\Facades\Route;

/**
 * Catch-all: una sola ruta sirve las 155 rutas de YepAPI con el mismo path
 * exacto bajo el prefijo fijo /hub (ej. /hub/v1/ai/chat, /hub/v1/scrape).
 * El middleware `aero.api` (sin scope fijo) solo autentica/rate-limita la
 * ApiKey; ProxyController valida el scope real (hub.<categoría>) a mano,
 * porque varía por endpoint y no se puede fijar en la ruta.
 */
Route::any('hub/{path}', [ProxyController::class, 'handle'])
    ->where('path', '.*')
    ->middleware('aero.api');
