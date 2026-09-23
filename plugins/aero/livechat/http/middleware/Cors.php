<?php namespace Aero\Livechat\Http\Middleware;

use Closure;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Throwable;

/**
 * El widget se embebe en dominios de terceros (el sitio de cada tenant), no
 * en micro.clouds.com.bo — sin CORS el navegador bloquea el fetch. Todos los
 * endpoints de este grupo son públicos y sin cookies/sesión, así que
 * Access-Control-Allow-Origin: * no expone nada sensible.
 */
class Cors
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->getMethod() === 'OPTIONS') {
            return response('', 204)->withHeaders($this->headers());
        }

        try {
            $response = $next($request);
        }
        catch (Throwable $e) {
            // `throttle` (u otro middleware más adentro) corta con una
            // excepción (ej. 429) ANTES de volver hasta acá — sin este
            // catch, esa respuesta sale sin headers de CORS y el navegador
            // la bloquea entera: el widget ve un error de red genérico en
            // vez del mensaje real ("demasiados intentos").
            $response = app(ExceptionHandler::class)->render($request, $e);
        }

        foreach ($this->headers() as $key => $value) {
            $response->headers->set($key, $value);
        }

        return $response;
    }

    protected function headers(): array
    {
        return [
            'Access-Control-Allow-Origin'  => '*',
            'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Accept',
        ];
    }
}
