<?php namespace Aero\Social\Classes\Oauth;

use Aero\Social\Classes\SocialException;

/** TikTok (Login Kit v2 web + Content Posting API). */
class TikTokProvider implements ProviderInterface
{
    public const SCOPES = ['user.info.basic', 'video.publish', 'video.upload'];

    public function network(): string
    {
        return 'tiktok';
    }

    public function authorizeUrl(array $creds, string $state, string $redirectUri, string $challenge): string
    {
        // Web: TikTok no usa PKCE (solo móvil/escritorio).
        return 'https://www.tiktok.com/v2/auth/authorize/?' . http_build_query([
            'client_key'    => $creds['client_id'],
            'scope'         => implode(',', self::SCOPES),
            'response_type' => 'code',
            'redirect_uri'  => $redirectUri,
            'state'         => $state,
        ]);
    }

    public function exchange(array $creds, string $code, string $redirectUri, string $verifier): array
    {
        return $this->token($creds, ['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => $redirectUri]);
    }

    public function refresh(array $creds, string $refreshToken): array
    {
        return $this->token($creds, ['grant_type' => 'refresh_token', 'refresh_token' => $refreshToken]);
    }

    protected function token(array $creds, array $params): array
    {
        $res = Http::run('TikTok', fn () => Http::client()->asForm()->post('https://open.tiktokapis.com/v2/oauth/token/', $params + [
            'client_key' => $creds['client_id'], 'client_secret' => $creds['client_secret'],
        ]));

        return Http::token('TikTok', $res);
    }

    public function accounts(string $accessToken): array
    {
        $res = Http::run('TikTok', fn () => Http::client()->withToken($accessToken)
            ->get('https://open.tiktokapis.com/v2/user/info/', ['fields' => 'open_id,avatar_url,display_name']));

        $user = $res->json('data.user');
        if (!$res->successful() || empty($user['open_id'])) {
            throw new SocialException('TikTok: ' . ($res->json('error.message') ?: 'no se pudo leer la cuenta.'));
        }

        return [[
            'external_id' => (string) $user['open_id'],
            'name'        => $user['display_name'] ?? null,
            'username'    => null,
            'avatar_url'  => $user['avatar_url'] ?? null,
        ]];
    }
}
