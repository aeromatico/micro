<?php

use Aero\Services\Http\Controllers\MenuController;
use Illuminate\Support\Facades\Route;

// Público y de solo lectura (mismos datos que el megamenú); throttle por IP.
Route::get('api/v1/services/menu', [MenuController::class, 'index'])->middleware('throttle:60,1');
