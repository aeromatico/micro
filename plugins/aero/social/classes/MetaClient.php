<?php namespace Aero\Social\Classes;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** Cliente mínimo de la Graph API de Meta (Facebook + Instagram). */
class MetaClient
{
    public static function appId(): ?string
    {
        return config('aero.social::meta.app_id');
    }

    public static function appSecret(): ?string
    {
        return config('aero.social::meta.app_secret');
    }

    public static function isConfigured(): bool
    {
        return (bool) (self::appId() && self::appSecret());
    }

    public static function version(): string
    {
        return config('aero.social::meta.graph_version') ?: 'v21.0';
    }

    public static function base(): string
    {
        return 'https://graph.facebook.com/' . self::version();
    }

    public static function get(string $path, array $query, ?string $token = null, int $timeout = 25): array
    {
        return self::send('get', $path, $query, $token, $timeout);
    }

    public static function post(string $path, array $params, ?string $token = null, int $timeout = 25): array
    {
        return self::send('post', $path, $params, $token, $timeout);
    }

    /** Recorre la paginación de Graph (`paging.next`) hasta $max filas. */
    public static function getAll(string $path, array $query, string $token, int $max = 2000): array
    {
        $rows = [];
        $query['limit'] = $query['limit'] ?? 200;

        do {
            $res = self::get($path, $query, $token, 40);
            $rows = array_merge($rows, $res['data'] ?? []);
            $after = $res['paging']['cursors']['after'] ?? null;
            $more = !empty($res['paging']['next']) && $after && count($rows) < $max;
            $query['after'] = $after;
        } while ($more);

        return $rows;
    }

    protected static function send(string $method, string $path, array $params, ?string $token, int $timeout): array
    {
        if ($token) {
            $params['access_token'] = $token;
            // appsecret_proof: Meta rechaza tokens robados usados sin el secreto de la app.
            if (self::appSecret()) {
                $params['appsecret_proof'] = hash_hmac('sha256', $token, self::appSecret());
            }
        }

        try {
            $request = Http::connectTimeout(5)->timeout($timeout)->acceptJson();
            $response = $method === 'get'
                ? $request->get(self::base() . '/' . ltrim($path, '/'), $params)
                : $request->asForm()->post(self::base() . '/' . ltrim($path, '/'), $params);
        } catch (ConnectionException $e) {
            throw new SocialException('Meta no respondió a tiempo. Se puede reintentar.');
        }

        $json = $response->json() ?? [];

        if ($response->successful() && !isset($json['error'])) {
            return $json;
        }

        $error = $json['error'] ?? [];
        $message = $error['error_user_msg'] ?? $error['message'] ?? ('Error HTTP ' . $response->status());
        $code = (int) ($error['code'] ?? 0);

        // 190/102 = token vencido o revocado; 10/200 con «permission» = permiso retirado.
        $tokenDead = in_array($code, [190, 102], true)
            || (in_array($code, [10, 200], true) && str_contains(strtolower($message), 'permission'));

        if ($tokenDead) {
            throw SocialException::tokenInvalid('La conexión con Meta ya no es válida (' . $message . '). Vuelve a conectar la cuenta.');
        }

        throw new SocialException('Meta: ' . $message);
    }
}
