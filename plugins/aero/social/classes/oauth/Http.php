<?php namespace Aero\Social\Classes\Oauth;

use Aero\Social\Classes\SocialException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http as LaravelHttp;

/** Peticiones con timeouts de plataforma y errores legibles. */
class Http
{
    public static function client(int $timeout = 25): PendingRequest
    {
        return LaravelHttp::connectTimeout(5)->timeout($timeout)->acceptJson();
    }

    /** Ejecuta y devuelve la respuesta; convierte fallos de red en SocialException. */
    public static function run(string $label, \Closure $call): Response
    {
        try {
            return $call();
        } catch (ConnectionException $e) {
            throw new SocialException("{$label} no respondió a tiempo. Se puede reintentar.");
        }
    }

    /** Token: 400/401 con invalid_grant = hay que reconectar. */
    public static function token(string $label, Response $res): array
    {
        $json = $res->json() ?? [];
        if ($res->successful() && !empty($json['access_token'])) {
            return $json;
        }

        $error = $json['error'] ?? null;
        $desc = $json['error_description'] ?? (is_string($error) ? $error : null) ?? ('HTTP ' . $res->status());

        if (in_array($error, ['invalid_grant', 'invalid_token', 'unauthorized_client'], true) || in_array($res->status(), [400, 401], true)) {
            throw SocialException::tokenInvalid("{$label} rechazó la conexión ({$desc}). Vuelve a conectar la cuenta.");
        }

        throw new SocialException("{$label}: {$desc}");
    }
}
