<?php namespace Aero\Social\Classes;

use Aero\Social\Classes\Oauth\ProviderInterface;
use Illuminate\Support\Str;

/**
 * Botón «Conectar» para redes OAuth2 (YouTube, X, TikTok). Misma URI de
 * retorno que Meta; el `state` dice a qué flujo pertenece cada retorno.
 */
class Connect
{
    public const SESSION_KEY = 'aero_social.connect';

    public const PROVIDERS = [
        'youtube' => Oauth\GoogleProvider::class,
        'x'       => Oauth\XProvider::class,
        'tiktok'  => Oauth\TikTokProvider::class,
    ];

    public static function provider(string $network): ?ProviderInterface
    {
        $class = self::PROVIDERS[$network] ?? null;

        return $class ? new $class() : null;
    }

    public static function ready(?int $tenantId, string $network): bool
    {
        return isset(self::PROVIDERS[$network]) && Credentials::ready(Credentials::for($tenantId, $network));
    }

    public static function authorizeUrl(string $network, int $tenantId, int $userId): string
    {
        $provider = self::provider($network) ?? throw new SocialException('Red no disponible.');
        $creds = Credentials::for($tenantId, $network);
        if (!Credentials::ready($creds)) {
            throw new SocialException('La conexión con ' . Networks::label($network) . ' aún no está habilitada. Puedes cargar las llaves de tu propia app en «Llaves propias».');
        }

        $state = Str::random(40);
        $verifier = Str::random(64);
        session()->put(self::SESSION_KEY, [
            'state' => $state, 'verifier' => $verifier, 'network' => $network,
            'tenant_id' => $tenantId, 'user_id' => $userId, 'app_id' => $creds['app_id'],
        ]);

        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        return $provider->authorizeUrl($creds, $state, MetaConnect::redirectUri(), $challenge);
    }

    /** ¿Este retorno es de un flujo OAuth2 (y no de Meta)? */
    public static function owns(?string $state): bool
    {
        $pending = session()->get(self::SESSION_KEY);

        return $pending && $state && hash_equals((string) $pending['state'], $state);
    }

    /**
     * @return array{network:string, connected:int, skipped:int}
     * @throws SocialException
     */
    public static function callback(array $query, int $tenantId, int $userId): array
    {
        $pending = session()->pull(self::SESSION_KEY);

        if (!$pending
            || !hash_equals((string) $pending['state'], (string) ($query['state'] ?? ''))
            || (int) $pending['tenant_id'] !== $tenantId
            || (int) $pending['user_id'] !== $userId) {
            throw new SocialException('La conexión expiró o no es válida. Inténtalo otra vez.');
        }

        $network = $pending['network'];
        $label = Networks::label($network);
        if (!empty($query['error']) || empty($query['code'])) {
            throw new SocialException("Se canceló la conexión con {$label}.");
        }

        $provider = self::provider($network);
        $creds = $pending['app_id'] && ($app = \Aero\Social\Models\App::find($pending['app_id']))
            ? ['client_id' => $app->client_id, 'client_secret' => $app->client_secret, 'app_id' => $app->id]
            : Credentials::platform($network);

        $token = $provider->exchange($creds, (string) $query['code'], MetaConnect::redirectUri(), $pending['verifier']);

        return ['network' => $network] + self::store($network, $provider->accounts($token['access_token']), $token, $tenantId, $userId, $creds['app_id']);
    }

    /** Guarda las cuentas autorizadas con su token. Público para tests. */
    public static function store(string $network, array $accounts, array $token, int $tenantId, int $userId, ?int $appId): array
    {
        $sync = new AccountSync($tenantId);
        $connected = 0;

        foreach ($accounts as $acc) {
            if (!$sync->fits($network, $acc['external_id'])) {
                continue;
            }
            $sync->upsert($network, $acc['external_id'], [
                'name'             => $acc['name'],
                'username'         => $acc['username'],
                'avatar_url'       => $acc['avatar_url'],
                'access_token'     => $token['access_token'],
                'refresh_token'    => $token['refresh_token'] ?? null,
                'token_expires_at' => isset($token['expires_in']) ? now()->addSeconds((int) $token['expires_in']) : null,
                'app_id'           => $appId,
                'connected_by'     => $userId,
            ]);
            $connected++;
        }

        return ['connected' => $connected, 'skipped' => $sync->skipped];
    }
}
