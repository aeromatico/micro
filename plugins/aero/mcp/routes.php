<?php

use Aero\Mcp\Http\Controllers\Api\McpController;
use Illuminate\Support\Facades\Route;

// El middleware `aero.api` (aero/api) exige `mcp.use`; cada tool exige además `mcp.tool.<nombre>`.
Route::prefix('api/v1')->middleware(['aero.api:mcp.use'])->group(function () {
    Route::post('mcp', [McpController::class, 'post']);
});
