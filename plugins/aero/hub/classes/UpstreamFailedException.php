<?php namespace Aero\Hub\Classes;

use Aero\Connector\Classes\ConnectorResponse;
use RuntimeException;

/**
 * `Credits::attempt()` solo reembolsa el hold si el callback lanza una
 * excepción — un `ConnectorResponse` con `successful=false` (4xx/5xx de
 * YepAPI) vuelve normalmente, sin tronar, así que sin esto el tenant queda
 * cobrado por una llamada que en realidad falló. `ProxyController` lanza esto
 * dentro del callback para forzar el reembolso, y lo captura afuera para
 * reenviar la respuesta real de YepAPI (no un 500 genérico).
 */
class UpstreamFailedException extends RuntimeException
{
    public function __construct(public readonly ConnectorResponse $response)
    {
        parent::__construct($response->error ?: "YepAPI respondió {$response->statusCode}");
    }
}
