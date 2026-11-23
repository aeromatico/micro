<?php namespace Aero\Social\Classes;

use Aero\Social\Models\Account;
use Cache;

/**
 * Token vigente de una cuenta. Renueva con el refresh_token si vence en menos
 * de 2 minutos. Bajo lock: X rota el refresh_token y dos renovaciones
 * simultáneas invalidarían la conexión.
 */
class Tokens
{
    public static function fresh(Account $account): string
    {
        if (!$account->token_expires_at || $account->token_expires_at->gt(now()->addMinutes(2))) {
            return (string) $account->access_token;
        }

        return Cache::lock("aero.social.refresh.{$account->id}", 30)->block(20, function () use ($account) {
            $account->refresh();   // otro proceso pudo renovarlo mientras esperábamos
            if ($account->token_expires_at && $account->token_expires_at->gt(now()->addMinutes(2))) {
                return (string) $account->access_token;
            }

            $provider = Connect::provider($account->network);
            if (!$provider || !$account->refresh_token) {
                throw SocialException::tokenInvalid('La conexión con ' . $account->networkLabel() . ' venció. Vuelve a conectar la cuenta.');
            }

            $token = $provider->refresh(Credentials::forAccount($account), $account->refresh_token);

            $account->access_token = $token['access_token'];
            if (!empty($token['refresh_token'])) {
                $account->refresh_token = $token['refresh_token'];
            }
            $account->token_expires_at = now()->addSeconds((int) ($token['expires_in'] ?? 3600));
            $account->save();

            return (string) $account->access_token;
        });
    }
}
