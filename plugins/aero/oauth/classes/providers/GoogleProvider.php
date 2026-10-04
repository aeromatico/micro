<?php namespace Aero\Oauth\Classes\Providers;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleProvider implements ProviderInterface
{
    public function code(): string
    {
        return 'google';
    }

    public function label(): string
    {
        return 'Google';
    }

    protected function clientId(): ?string
    {
        return config('aero.oauth::google.client_id') ?: config('services.google.client_id');
    }

    protected function clientSecret(): ?string
    {
        return config('aero.oauth::google.client_secret') ?: config('services.google.client_secret');
    }

    public function isConfigured(): bool
    {
        return (bool) ($this->clientId() && $this->clientSecret());
    }

    public function loginScopes(): array
    {
        return ['openid', 'email', 'profile'];
    }

    public function authorizeUrl(string $state, string $redirectUri, array $scopes, string $codeChallenge, array $extra = []): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query(array_filter([
            'client_id'              => $this->clientId(),
            'redirect_uri'           => $redirectUri,
            'response_type'          => 'code',
            'scope'                  => implode(' ', array_unique($scopes)),
            'state'                  => $state,
            'code_challenge'         => $codeChallenge,
            'code_challenge_method'  => 'S256',
            'access_type'            => 'offline',      // refresh_token
            'include_granted_scopes' => 'true',         // permisos incrementales
            // Solo se fuerza el consentimiento cuando se piden permisos extra
            // (así Google devuelve refresh_token); el login simple no lo repite.
            'prompt'                 => count(array_diff($scopes, $this->loginScopes())) ? 'consent' : null,
        ] + $extra));
    }

    public function exchangeCode(string $code, string $redirectUri, string $codeVerifier): array
    {
        return $this->token([
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => $redirectUri,
            'code_verifier' => $codeVerifier,
        ]);
    }

    public function refresh(string $refreshToken): array
    {
        return $this->token(['grant_type' => 'refresh_token', 'refresh_token' => $refreshToken]);
    }

    protected function token(array $params): array
    {
        $response = Http::asForm()->timeout(20)->post('https://oauth2.googleapis.com/token', $params + [
            'client_id'     => $this->clientId(),
            'client_secret' => $this->clientSecret(),
        ]);

        if (!$response->successful()) {
            throw new RuntimeException('Google rechazó el token: ' . ($response->json('error_description') ?? $response->json('error') ?? $response->status()));
        }

        return $response->json();
    }

    public function profile(string $accessToken): array
    {
        $response = Http::withToken($accessToken)->timeout(20)->get('https://openidconnect.googleapis.com/v1/userinfo');

        if (!$response->successful() || !$response->json('sub')) {
            throw new RuntimeException('No se pudo leer el perfil de Google.');
        }

        return [
            'id'             => (string) $response->json('sub'),
            'email'          => $response->json('email'),
            'email_verified' => (bool) $response->json('email_verified'),
            'name'           => $response->json('name'),
            'avatar'         => $response->json('picture'),
        ];
    }

    public function revoke(string $token): void
    {
        Http::asForm()->timeout(10)->post('https://oauth2.googleapis.com/revoke', ['token' => $token]);
    }
}
