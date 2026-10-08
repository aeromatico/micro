<?php

use Aero\Hub\Http\Controllers\Api\ProxyController;
use Illuminate\Support\Facades\Route;

/**
 * Catch-all: una sola ruta sirve las 155 rutas de YepAPI con el mismo path
 * exacto bajo el prefijo fijo /hub (ej. /hub/v1/ai/chat, /hub/v1/scrape).
 * El middleware `aero.api` (sin scope fijo) solo autentica/rate-limita la
 * ApiKey; ProxyController valida el scope real (hub.<categoría>) a mano,
 * porque varía por endpoint y no se puede fijar en la ruta.
 *
 * El patrón exige el prefijo "v1/" (todos los paths de YepAPI empiezan así,
 * verificado: los 155 endpoints) en vez de ".*" — con ".*" este catch-all se
 * comía CUALQUIER cosa bajo /hub/, incluidas las páginas públicas del theme
 * "master" (/hub/apis, /hub/modelos): Laravel resuelve las rutas de plugin
 * antes que el fallback de CMS, así que esas dos páginas devolvían el 401
 * de ProxyController ("Falta una API key...") en vez de renderizar.
 */
Route::any('hub/{path}', [ProxyController::class, 'handle'])
    ->where('path', 'v1/.*')
    ->middleware('aero.api');
