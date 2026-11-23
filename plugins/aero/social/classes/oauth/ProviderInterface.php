<?php namespace Aero\Social\Classes\Oauth;

/**
 * Conexión OAuth2 de una red con tokens renovables. Los tokens devueltos se
 * normalizan a: access_token, refresh_token?, expires_in?.
 */
interface ProviderInterface
{
    public function network(): string;

    public function authorizeUrl(array $creds, string $state, string $redirectUri, string $challenge): string;

    public function exchange(array $creds, string $code, string $redirectUri, string $verifier): array;

    public function refresh(array $creds, string $refreshToken): array;

    /**
     * Cuentas que autorizó la persona.
     * @return array<int, array{external_id:string, name:?string, username:?string, avatar_url:?string}>
     */
    public function accounts(string $accessToken): array;
}
