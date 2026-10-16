<?php

use Aero\Mcp\Http\Controllers\Api\McpController;
use Illuminate\Support\Facades\Route;

// El middleware `aero.api` (aero/api) exige `mcp.use`; cada tool exige además `mcp.tool.<nombre>`.
Route::prefix('api/v1')->middleware(['aero.api:mcp.use'])->group(function () {
    Route::post('mcp', [McpController::class, 'post']);
});

// Endpoint corto para clientes MCP (Claude, Codex, Cursor…): https://market.com.bo/mcp
Route::post('mcp', [McpController::class, 'post'])->middleware(['aero.api:mcp.use']);

// Streamable HTTP: este servidor no abre flujos SSE, así que un cliente que lo pida recibe 405.
// Una persona que abre /mcp en el navegador va a la documentación.
Route::get('mcp', function (\Illuminate\Http\Request $request) {
    if ($request->headers->has('Mcp-Session-Id') || str_contains((string) $request->header('Accept'), 'text/event-stream')) {
        return response()->json(['error' => 'method_not_allowed', 'message' => 'Usa POST con JSON-RPC.'], 405, ['Allow' => 'POST']);
    }

    return redirect('/docs/mcp', 302);
});
