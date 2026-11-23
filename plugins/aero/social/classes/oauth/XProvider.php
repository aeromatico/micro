<?php namespace Aero\Social\Classes\Oauth;

use Aero\Social\Classes\SocialException;

/** X: OAuth 2.0 con PKCE, cliente confidencial (Basic auth al pedir tokens). */
class XProvider implements ProviderInterface
{
    public const SCOPES = ['tweet.read', 'tweet.write', 'users.read', 'media.write', 'offline.access'];

    public function network(): string
    {
        return 'x';
    }

    public function authorizeUrl(array $creds, string $state, string $redirectUri, string $challenge): string
    {
        return 'https://x.com/i/oauth2/authorize?' . http_build_query([
            'response_type'         => 'code',
            'client_id'             => $creds['client_id'],
            'redirect_uri'          => $redirectUri,
            'scope'                 => implode(' ', self::SCOPES),
            'state'                 => $state,
            'code_challenge'        => $challenge,
            'code_challenge_method' => 'S256',
        ]);
    }

    public function exchange(array $creds, string $code, string $redirectUri, string $verifier): array
    {
        return $this->token($creds, [
            'grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => $redirectUri, 'code_verifier' => $verifier,
        ]);
    }

    /** X rota el refresh_token: el nuevo reemplaza al viejo. */
    public function refresh(array $creds, string $refreshToken): array
    {
        return $this->token($creds, ['grant_type' => 'refresh_token', 'refresh_token' => $refreshToken]);
    }

    protected function token(array $creds, array $params): array
    {
        $res = Http::run('X', fn () => Http::client()->asForm()
            ->withBasicAuth($creds['client_id'], $creds['client_secret'])
            ->post('https://api.x.com/2/oauth2/token', $params + ['client_id' => $creds['client_id']]));

        return Http::token('X', $res);
    }

    public function accounts(string $accessToken): array
    {
        $res = Http::run('X', fn () => Http::client()->withToken($accessToken)
            ->get('https://api.x.com/2/users/me', ['user.fields' => 'profile_image_url,username,name']));

        $user = $res->json('data');
        if (!$res->successful() || empty($user['id'])) {
            throw new SocialException('X: ' . ($res->json('detail') ?? $res->json('title') ?? 'no se pudo leer la cuenta.'));
        }

        return [[
            'external_id' => (string) $user['id'],
            'name'        => $user['name'] ?? null,
            'username'    => $user['username'] ?? null,
            'avatar_url'  => $user['profile_image_url'] ?? null,
        ]];
    }
}
