<?php

use Aero\Clinica\Http\Controllers\Api\PatientsController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/clinica')->middleware('aero.api:clinica.patients.read')->group(function () {
    Route::get('patients', [PatientsController::class, 'index']);
    Route::get('patients/{id}', [PatientsController::class, 'show'])->whereNumber('id');
});
