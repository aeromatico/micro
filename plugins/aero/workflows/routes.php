<?php

use Aero\Workflows\Classes\WorkflowRunner;
use Aero\Workflows\Models\Workflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Webhook entrante. Falla cerrado: el workflow debe estar activo, ser de tipo
 * webhook y tener `secret`. Firma HMAC en X-Signature (sha256=<hex> del body)
 * o el secreto tal cual en X-Webhook-Token.
 */
Route::post('workflows/hook/{id}', function (Request $request, int $id) {
    $workflow = Workflow::where('id', $id)->where('is_active', true)->where('status', 'published')->where('trigger_type', 'webhook')->first();
    $secret = (string) ($workflow?->jsonField('trigger_config')['secret'] ?? '');

    if (!$workflow || $secret === '') {
        return response()->json(['error' => 'not_found'], 404);
    }

    $body = $request->getContent();

    if (strlen($body) > 262144) {
        return response()->json(['error' => 'payload_too_large'], 413);
    }

    $signature = (string) $request->header('X-Signature', '');
    $token = (string) $request->header('X-Webhook-Token', '');
    $valid = ($signature !== '' && hash_equals('sha256=' . hash_hmac('sha256', $body, $secret), $signature))
        || ($token !== '' && hash_equals($secret, $token));

    if (!$valid) {
        return response()->json(['error' => 'invalid_signature'], 401);
    }

    $run = WorkflowRunner::start($workflow, (array) $request->json()->all(), 'webhook');

    return $run
        ? response()->json(['run_id' => $run->id], 202)
        : response()->json(['error' => 'rate_limited'], 429);
})->middleware('throttle:60,1');
