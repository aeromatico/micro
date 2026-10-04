<?php namespace Aero\Oauth\Models;

use Illuminate\Support\Facades\Crypt;
use Model;

/**
 * Cuenta de un proveedor externo vinculada a un usuario del backend. Los
 * tokens se guardan cifrados con la APP_KEY (nunca en SettingsModel).
 */
class Identity extends Model
{
    public $table = 'aero_oauth_identities';

    public $fillable = ['backend_user_id', 'tenant_id', 'provider', 'provider_user_id', 'email', 'name', 'avatar_url', 'last_login_at'];

    public $jsonable = ['granted_scopes'];

    protected $dates = ['token_expires_at', 'last_login_at'];

    protected $hidden = ['access_token', 'refresh_token'];

    public $belongsTo = [
        'user' => [\Backend\Models\User::class, 'key' => 'backend_user_id'],
    ];

    public function setAccessTokenAttribute($value): void
    {
        $this->attributes['access_token'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getAccessTokenAttribute($value): ?string
    {
        return $this->decrypt($value);
    }

    public function setRefreshTokenAttribute($value): void
    {
        // Google solo entrega refresh_token la primera vez: nunca lo pises con null.
        if ($value) {
            $this->attributes['refresh_token'] = Crypt::encryptString($value);
        }
    }

    public function getRefreshTokenAttribute($value): ?string
    {
        return $this->decrypt($value);
    }

    protected function decrypt($value): ?string
    {
        if (!$value) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return null;
        }
    }

    public function hasScopes(array $scopes): bool
    {
        return !array_diff($scopes, (array) ($this->granted_scopes ?? []));
    }
}
