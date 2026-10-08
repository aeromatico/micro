<?php namespace Aero\Livechat\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** La API pública solo habla JSON: sin esto, un error de validación devuelve la página HTML de October. */
class ForceJson
{
    public function handle(Request $request, Closure $next)
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
