<?php namespace Aero\Workflows\Classes;

use Http;
use Throwable;

/**
 * Guard SSRF para los nodos que llaman a una URL escrita por el tenant:
 * solo https, sin usuario/clave, rechaza IPs privadas/reservadas, fija la IP
 * ya validada (anti DNS-rebinding), sin redirects, con timeout y tope de tamaño.
 */
class SafeUrl
{
    public const TIMEOUT = 10;
    public const MAX_BYTES = 1048576;

    /** @return array{0: bool, 1: string, 2: array} [ok, error, ips] */
    public static function validate(string $url): array
    {
        $parts = parse_url($url);

        if (!$parts || strtolower($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
            return [false, 'La URL debe ser https.', []];
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return [false, 'La URL no puede llevar usuario ni contraseña.', []];
        }

        $host = $parts['host'];
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);

        if (!$ips) {
            return [false, "No se pudo resolver el dominio {$host}.", []];
        }

        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return [false, 'La URL apunta a una dirección privada o reservada.', []];
            }
        }

        return [true, '', $ips];
    }

    /**
     * @return array{ok: bool, status: ?int, body: mixed, error: ?string, headers: array}
     */
    public static function request(string $method, string $url, array $payload = [], array $headers = []): array
    {
        [$ok, $error, $ips] = static::validate($url);

        if (!$ok) {
            return ['ok' => false, 'status' => null, 'body' => null, 'error' => $error, 'headers' => []];
        }

        $parts = parse_url($url);
        $port = $parts['port'] ?? 443;

        try {
            $request = Http::withHeaders($headers)
                ->timeout(static::TIMEOUT)
                ->withOptions([
                    'allow_redirects' => false,
                    'curl' => [CURLOPT_RESOLVE => ["{$parts['host']}:{$port}:{$ips[0]}"]],
                ]);

            $method = strtoupper($method);
            $response = match ($method) {
                'GET'    => $request->get($url, $payload),
                'DELETE' => $request->delete($url, $payload),
                'PUT'    => $request->put($url, $payload),
                'PATCH'  => $request->patch($url, $payload),
                default  => $request->post($url, $payload),
            };

            $raw = substr($response->body(), 0, static::MAX_BYTES);

            return [
                'ok'     => $response->successful(),
                'status' => $response->status(),
                'body'   => json_decode($raw, true) ?? $raw,
                'error'  => $response->successful() ? null : 'HTTP ' . $response->status(),
                'headers' => $response->headers(),
            ];
        }
        catch (Throwable $e) {
            return ['ok' => false, 'status' => null, 'body' => null, 'error' => $e->getMessage(), 'headers' => []];
        }
    }
}
