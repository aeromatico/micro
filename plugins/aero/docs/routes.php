<?php

use Aero\Docs\Classes\DocsScope;
use Aero\Docs\Models\Guide;
use Illuminate\Support\Facades\Route;

/**
 * HTML crudo de una guía publicada, pensado para incrustarse en un iframe
 * `sandbox="allow-scripts"` desde /guias/{slug}. La CSP es la red de seguridad
 * por si el HTML (generado por IA) intenta cargar algo que no debe: solo se
 * permiten los scripts de cdnjs y las fuentes de Google. La URL lleva ?v=<hash>
 * y por eso la respuesta se puede cachear sin riesgo de servir una versión vieja.
 */
Route::get('guias/{slug}/embed', function (string $slug) {
    $guide = Guide::published()->visibleIn(DocsScope::currentTenantId())
        ->where('slug', $slug)->first();

    abort_unless($guide, 404);

    return response($guide->html, 200, [
        'Content-Type'            => 'text/html; charset=utf-8',
        'Content-Security-Policy' => "default-src 'none'; script-src 'unsafe-inline' https://cdnjs.cloudflare.com; "
            . "style-src 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; "
            . "img-src data: blob:; frame-ancestors 'self'; sandbox allow-scripts",
        'X-Content-Type-Options'  => 'nosniff',
        'Cache-Control'           => 'public, max-age=31536000, immutable',
        'ETag'                    => '"' . $guide->html_hash . '"',
    ]);
})->where('slug', '[A-Za-z0-9_-]+')->middleware('web');
