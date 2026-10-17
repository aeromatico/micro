<?php

use Aero\Medical\Http\Controllers\Api\PatientsController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/medical')->middleware('aero.api:medical.patients.read')->group(function () {
    Route::get('patients', [PatientsController::class, 'index']);
    Route::get('patients/{id}', [PatientsController::class, 'show'])->whereNumber('id');
});
