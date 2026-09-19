<?php namespace Aero\Wapi\Classes;

use Aero\Hello\Models\Profile;
use Aero\Wapi\Models\Settings;

/**
 * Mismo criterio que Aero\Hello\Classes\CredentialResolver::zernioApiKey():
 * el perfil manda solo si declara use_own_credentials y trae su propia key de
 * wapi; si no, la global de Configuración → wapi.
 */
class WapiCredentials
{
    public static function apiKey(?Profile $profile = null): ?string
    {
        if ($profile && $profile->use_own_credentials && $profile->wapi_api_key) {
            return $profile->wapi_api_key;
        }

        return Settings::getApiKey();
    }
}
