<?php

use Aero\Tracking\Http\Controllers\Api\AssetsController;
use Aero\Tracking\Http\Controllers\Api\IngestController;
use Aero\Tracking\Http\Controllers\Api\JobsController;
use Aero\Tracking\Http\Controllers\Api\TrackingController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/tracking')->group(function () {
    // Dispositivos (OwnTracks HTTP): el token del activo va en la URL, fuera del gateway.
    Route::post('ingest/{token}', [IngestController::class, 'owntracks'])->middleware('throttle:240,1');

    Route::middleware('aero.api:tracking.assets.read')->group(function () {
        Route::get('assets', [AssetsController::class, 'index']);
        Route::get('assets/{id}', [AssetsController::class, 'show'])->whereNumber('id');
    });

    Route::middleware('aero.api:tracking.assets.write')->group(function () {
        Route::post('assets', [AssetsController::class, 'store']);
        Route::match(['put', 'patch'], 'assets/{id}', [AssetsController::class, 'update'])->whereNumber('id');
        Route::delete('assets/{id}', [AssetsController::class, 'destroy'])->whereNumber('id');
        Route::post('assets/{id}/regenerate-token', [AssetsController::class, 'regenerateToken'])->whereNumber('id');
    });

    Route::middleware('aero.api:tracking.jobs.read')->group(function () {
        Route::get('jobs', [JobsController::class, 'index']);
        Route::get('jobs/{uuid}', [JobsController::class, 'show']);
    });

    Route::middleware('aero.api:tracking.jobs.write')->group(function () {
        Route::post('jobs', [JobsController::class, 'store']);
        Route::post('jobs/{uuid}/assign', [JobsController::class, 'assign']);
        Route::post('jobs/{uuid}/status', [JobsController::class, 'status']);
        Route::post('jobs/{uuid}/stops/{stopId}/status', [JobsController::class, 'stopStatus'])->whereNumber('stopId');
    });

    Route::middleware('aero.api:tracking.positions.read')->group(function () {
        Route::get('tracking/live', [TrackingController::class, 'live']);
        Route::get('assets/{id}/positions', [TrackingController::class, 'history'])->whereNumber('id');
    });

    Route::middleware('aero.api:tracking.positions.write')->group(function () {
        Route::post('assets/{id}/position', [TrackingController::class, 'push'])->whereNumber('id');
    });
});
