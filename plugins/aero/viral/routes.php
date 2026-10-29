<?php

use Aero\Viral\Classes\Kyc\DiditWebhook;
use Aero\Viral\Classes\Kyc\KycService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Webhook de Didit. Responde rápido (Didit corta a los 5 s): firma → aplicar → 200.
 * Dominio habilitado en Cloudflare para la IP de entrega de Didit (18.203.201.92).
 */
Route::post('viral/webhooks/didit', function (Request $request) {
    $headers = [];
    foreach (['x-signature', 'x-signature-v2', 'x-signature-previous', 'x-signature-v2-previous', 'x-timestamp'] as $h) {
        $headers[$h] = $request->header($h);
    }

    if (!DiditWebhook::verify($request->getContent(), $headers, (string) config('services.didit.webhook_secret'))) {
        return response()->json(['ok' => false], 401);
    }

    $applied = (new KycService)->handleWebhook((array) json_decode($request->getContent(), true));

    return response()->json(['ok' => true, 'applied' => $applied]);
})->middleware(['web', 'throttle:120,1'])->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
