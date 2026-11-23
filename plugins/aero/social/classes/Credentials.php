<?php namespace Aero\Social\Classes;

use Aero\Social\Models\App;

/**
 * Qué app (client id/secret) usar para conectar o renovar una cuenta:
 * la propia del tenant si la cargó, si no la de Market (.env).
 */
class Credentials
{
    /** Redes donde el tenant puede usar su propia app. Meta va siempre con la de Market. */
    public const BYO = ['youtube', 'x', 'tiktok'];

    /** @return array{client_id:?string, client_secret:?string, app_id:?int} */
    public static function for(?int $tenantId, string $network): array
    {
        if ($tenantId && in_array($network, self::BYO, true)) {
            $app = App::where('tenant_id', $tenantId)->where('network', $network)->first();
            if ($app) {
                return self::fromApp($app);
            }
        }

        return self::platform($network);
    }

    /** Las mismas llaves con que se conectó la cuenta (la renovación exige el mismo cliente). */
    public static function forAccount(\Aero\Social\Models\Account $account): array
    {
        if ($account->app_id && ($app = App::find($account->app_id))) {
            return self::fromApp($app);
        }

        return self::platform($account->network);
    }

    public static function platform(string $network): array
    {
        $cfg = (array) config("aero.social::{$network}");

        return [
            'client_id'     => $cfg['client_id'] ?? $cfg['client_key'] ?? null,
            'client_secret' => $cfg['client_secret'] ?? null,
            'app_id'        => null,
        ];
    }

    public static function ready(array $creds): bool
    {
        return !empty($creds['client_id']) && !empty($creds['client_secret']);
    }

    protected static function fromApp(App $app): array
    {
        return ['client_id' => $app->client_id, 'client_secret' => $app->client_secret, 'app_id' => $app->id];
    }
}
