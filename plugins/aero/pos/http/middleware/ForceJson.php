<?php namespace Aero\Pos\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** La API siempre responde JSON, también en errores de validación (si no, October devuelve HTML 500). */
class ForceJson
{
    public function handle(Request $request, Closure $next)
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
