<?php namespace Aero\Livechat\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Reemplaza al `throttle` de Laravel para las rutas públicas del widget:
 * ese middleware tiene prioridad fija en el Kernel (ver
 * $middlewarePriority) y corre ANTES que Cors::class sin importar el orden
 * en que se listen — cuando corta con un 429 lo hace vía una excepción que
 * nunca pasa por Cors, así que esa respuesta sale sin headers de CORS y el
 * navegador la bloquea entera (el widget veía "no se pudo conectar" en vez
 * del mensaje real). Este middleware nunca lanza una excepción: siempre
 * devuelve una respuesta JSON normal, que si pasa por Cors después la
 * decora igual que cualquier otra.
 */
class ThrottleJson
{
    public function handle(Request $request, Closure $next, int $maxAttempts, int $decayMinutes = 1)
    {
        $key = 'livechat:' . $request->ip() . '|' . $request->path();

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return response()->json([
                'error'   => 'too_many_requests',
                'message' => 'Demasiados intentos. Esperá un momento y probá de nuevo.',
            ], 429);
        }

        RateLimiter::hit($key, $decayMinutes * 60);

        return $next($request);
    }
}
