<?php namespace Aero\Oauth\Classes;

use Aero\Oauth\Classes\Providers\ProviderRegistry;
use Aero\Oauth\Models\Identity;
use RuntimeException;

/**
 * API pública para otros plugins:
 *   Oauth::identityFor($userId, 'google')
 *   Oauth::connectUrl('google', ['sheets'], '/backend/aero/sheets/mappings')
 *   Oauth::accessToken($identity)      // refresca si hace falta
 */
class Oauth
{
    public static function identityFor(int $userId, string $provider = 'google'): ?Identity
    {
        return Identity::where('backend_user_id', $userId)->where('provider', $provider)->first();
    }

    public static function connectUrl(string $provider, array $scopeKeys, string $returnTo): string
    {
        return url('aero/oauth/' . $provider . '/redirect') . '?' . http_build_query([
            'purpose' => 'connect',
            'scopes'  => implode(',', $scopeKeys),
            'return'  => $returnTo,
        ]);
    }

    /** Token vigente (renueva con el refresh_token si expira en < 60 s). */
    public static function accessToken(Identity $identity): string
    {
        if ($identity->access_token && $identity->token_expires_at && $identity->token_expires_at->gt(now()->addSeconds(60))) {
            return $identity->access_token;
        }

        $provider = ProviderRegistry::get($identity->provider);
        if (!$provider || !$identity->refresh_token) {
            throw new RuntimeException('La conexión con ' . $identity->provider . ' expiró; vuelve a conectarla.');
        }

        $data = $provider->refresh($identity->refresh_token);
        $identity->access_token = $data['access_token'];
        $identity->token_expires_at = now()->addSeconds((int) ($data['expires_in'] ?? 3600));
        $identity->save();

        return $identity->access_token;
    }

    /** Solo rutas locales: evita redirecciones abiertas. */
    public static function safeReturn(?string $path, string $fallback): string
    {
        if ($path && str_starts_with($path, '/') && !str_starts_with($path, '//') && !str_contains($path, '\\')) {
            return $path;
        }

        return $fallback;
    }
}
