<?php

use Aero\Rgpd\Models\Config;
use Aero\Rgpd\Models\Consent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Registro del consentimiento. Mismo origen que el sitio (no hay CORS). El
 * tenant NUNCA viene del cliente: se resuelve por el host de la petición.
 */
Route::post('rgpd/consent', function (Request $request) {
    $data = $request->validate([
        'id' => ['required', 'string', 'regex:/^[A-Za-z0-9-]{8,36}$/'],
        'a'  => 'required|boolean',
        'm'  => 'required|boolean',
    ]);

    $config = Config::forScope(Config::tenantIdForHost($request->getHost()));
    if (!$config || !$config->is_enabled || !$config->keep_log) {
        return response()->json(['ok' => true, 'logged' => false]);
    }

    Consent::create([
        'tenant_id'      => $config->tenant_id,
        'consent_id'     => $data['id'],
        'analytics'      => $config->use_analytics && $data['a'],
        'marketing'      => $config->use_marketing && $data['m'],
        'config_version' => $config->version,
        'ip_hash'        => hash_hmac('sha256', (string) $request->ip(), (string) config('app.key')),
        'user_agent'     => mb_substr((string) $request->userAgent(), 0, 255),
    ]);

    return response()->json(['ok' => true, 'logged' => true]);
})->middleware(['web', 'throttle:30,1'])->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
