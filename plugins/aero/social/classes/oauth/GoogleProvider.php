<?php namespace Aero\Social\Classes\Oauth;

use Aero\Social\Classes\SocialException;

/** YouTube: canales de la cuenta de Google (subida + lectura del canal). */
class GoogleProvider implements ProviderInterface
{
    public const SCOPES = [
        'https://www.googleapis.com/auth/youtube.upload',
        'https://www.googleapis.com/auth/youtube.readonly',
    ];

    public function network(): string
    {
        return 'youtube';
    }

    public function authorizeUrl(array $creds, string $state, string $redirectUri, string $challenge): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id'             => $creds['client_id'],
            'redirect_uri'          => $redirectUri,
            'response_type'         => 'code',
            'scope'                 => implode(' ', self::SCOPES),
            'state'                 => $state,
            'code_challenge'        => $challenge,
            'code_challenge_method' => 'S256',
            'access_type'           => 'offline',   // refresh_token
            'prompt'                => 'consent select_account',
        ]);
    }

    public function exchange(array $creds, string $code, string $redirectUri, string $verifier): array
    {
        return $this->token($creds, [
            'grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => $redirectUri, 'code_verifier' => $verifier,
        ]);
    }

    public function refresh(array $creds, string $refreshToken): array
    {
        return $this->token($creds, ['grant_type' => 'refresh_token', 'refresh_token' => $refreshToken]);
    }

    protected function token(array $creds, array $params): array
    {
        $res = Http::run('Google', fn () => Http::client()->asForm()->post('https://oauth2.googleapis.com/token', $params + [
            'client_id' => $creds['client_id'], 'client_secret' => $creds['client_secret'],
        ]));

        return Http::token('Google', $res);
    }

    public function accounts(string $accessToken): array
    {
        $res = Http::run('YouTube', fn () => Http::client()->withToken($accessToken)
            ->get('https://www.googleapis.com/youtube/v3/channels', ['part' => 'snippet', 'mine' => 'true']));

        if (!$res->successful()) {
            throw new SocialException('YouTube: ' . ($res->json('error.message') ?? 'no se pudo leer el canal.'));
        }

        $items = $res->json('items') ?? [];
        if (!$items) {
            throw new SocialException('Esa cuenta de Google no tiene un canal de YouTube. Créalo en youtube.com y vuelve a conectar.');
        }

        return array_map(fn ($c) => [
            'external_id' => (string) $c['id'],
            'name'        => $c['snippet']['title'] ?? null,
            'username'    => isset($c['snippet']['customUrl']) ? ltrim($c['snippet']['customUrl'], '@') : null,
            'avatar_url'  => $c['snippet']['thumbnails']['default']['url'] ?? null,
        ], $items);
    }
}
