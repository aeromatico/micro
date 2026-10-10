<?php

use Aero\Credits\Http\Controllers\Api\TrialsController;
use Illuminate\Support\Facades\Route;

// Sin Aero.Api no hay con qué autenticar: no se registra nada (dependencia blanda).
if (!class_exists(\Aero\Api\Classes\ApiAuth::class)) {
    return;
}

Route::prefix('api/v1/credits')->middleware('aero.api:credits.trials.issue')->group(function () {
    Route::post('trials', [TrialsController::class, 'store']);
    Route::get('trials/{ref}', [TrialsController::class, 'show'])->where('ref', '[A-Za-z0-9._:@\-]+');
});
