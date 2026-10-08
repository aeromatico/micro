<?php namespace Aero\Oauth\Classes\Providers;

interface ProviderInterface
{
    public function code(): string;

    public function label(): string;

    public function isConfigured(): bool;

    /** Scopes mínimos para identificar a la persona (login). */
    public function loginScopes(): array;

    /**
     * URL de autorización. $extra: parámetros específicos (p. ej. login_hint).
     */
    public function authorizeUrl(string $state, string $redirectUri, array $scopes, string $codeChallenge, array $extra = []): string;

    /** Intercambia el code. Devuelve access_token, refresh_token?, expires_in, scope. */
    public function exchangeCode(string $code, string $redirectUri, string $codeVerifier): array;

    public function refresh(string $refreshToken): array;

    /** Perfil normalizado: id, email, email_verified, name, avatar. */
    public function profile(string $accessToken): array;

    public function revoke(string $token): void;
}
