<?php namespace Aero\MasterAds\Models;

use Illuminate\Support\Facades\Crypt;
use October\Rain\Database\Model;

/**
 * MetaAccount — cuenta publicitaria Meta (`act_<id>`) conectada vía OAuth
 * a un Workspace del SaaS.
 *
 * Seguridad:
 *  - Los campos `access_token` y `refresh_token` se almacenan **cifrados**
 *    en BD mediante mutators que envuelven a `Crypt::encrypt`. Se exponen en
 *    texto plano sólo a través de los accessors equivalentes.
 *  - Ambos tokens están declarados en `$hidden`, por lo que JAMÁS aparecen
 *    en serializaciones JSON (`toArray()`, respuestas API, logs).
 *
 * Validates Requirements 2.2, 2.3, 2.4, 15.1, 15.2, 15.3, 17.1, 17.6.
 *
 * @property int                            $id
 * @property int                            $workspace_id
 * @property string                         $meta_act_id     formato `act_<digits>`
 * @property string|null                    $name
 * @property string                         $currency        ISO-4217 (3 chars)
 * @property string|null                    $access_token    descifrado al leer
 * @property string|null                    $refresh_token   descifrado al leer
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $last_synced_at
 * @property string|null                    $last_error
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class MetaAccount extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \Aero\MasterAds\Classes\Concerns\BelongsToTenantScope;

    /**
     * @var string table associated with the model
     */
    public $table = 'aero_masterads_meta_accounts';

    /**
     * @var array fillable attributes for mass assignment
     */
    protected $fillable = [
        'workspace_id',
        'meta_act_id',
        'name',
        'currency',
        'access_token',
        'refresh_token',
        'expires_at',
        'last_synced_at',
    ];

    /**
     * @var array hidden attributes excluded from JSON/array serialization
     *
     * Garantiza que los tokens cifrados (Requirement 15.3) nunca se exporten,
     * ni siquiera de forma inadvertida en logs o respuestas API.
     */
    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    /**
     * @var array dates mutated to Carbon instances
     */
    protected $dates = [
        'expires_at',
        'last_synced_at',
    ];

    /**
     * @var array rules validation rules (Requirement 2.4)
     */
    public $rules = [
        'workspace_id' => 'required|exists:aero_masterads_workspaces,id',
        'meta_act_id'  => 'required|regex:/^act_\d+$/',
        'currency'     => 'required|size:3',
    ];

    /**
     * @var array belongsTo relations
     */
    public $belongsTo = [
        'workspace' => Workspace::class,
    ];

    /**
     * @var array hasMany relations
     */
    public $hasMany = [
        'campaigns' => Campaign::class,
    ];

    //
    // Mutators — cifrado transparente de tokens (Requirements 15.1, 15.2)
    //

    /**
     * getAccessTokenAttribute descifra el access_token al acceder al atributo.
     *
     * Si el valor almacenado no es un payload cifrado válido (por ejemplo,
     * datos migrados desde una versión anterior sin cifrado), se devuelve el
     * valor crudo para no romper consumidores existentes.
     */
    public function getAccessTokenAttribute($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Crypt::decrypt($value);
        } catch (\Throwable $e) {
            // Fallback: el valor no estaba cifrado (no provocar errores fatales).
            return $value;
        }
    }

    /**
     * setAccessTokenAttribute cifra el access_token antes de persistirlo.
     */
    public function setAccessTokenAttribute($value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['access_token'] = null;
            return;
        }

        $this->attributes['access_token'] = Crypt::encrypt($value);
    }

    /**
     * getRefreshTokenAttribute descifra el refresh_token al acceder al atributo.
     */
    public function getRefreshTokenAttribute($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Crypt::decrypt($value);
        } catch (\Throwable $e) {
            return $value;
        }
    }

    /**
     * setRefreshTokenAttribute cifra el refresh_token antes de persistirlo.
     */
    public function setRefreshTokenAttribute($value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['refresh_token'] = null;
            return;
        }

        $this->attributes['refresh_token'] = Crypt::encrypt($value);
    }

    //
    // Domain helpers — gestión de expiración del token
    //

    /**
     * isTokenExpired indica si el access_token ya pasó su `expires_at`.
     *
     * Cuentas sin `expires_at` se consideran NO expiradas: el llamador debe
     * decidir si tratarlas como tokens de larga duración sin rotación o
     * forzar un refresh defensivo.
     */
    public function isTokenExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * expiresWithinDays indica si el token expira en menos de `$days` días
     * (incluye tokens ya expirados; ver `isTokenExpired()` para distinguir).
     *
     * Usado por `MetaApiClient` y `masterads:rotate-tokens` para decidir si
     * disparar un refresh proactivo (Requirement 2.7: refresh si quedan < 7 días).
     */
    public function expiresWithinDays(int $days): bool
    {
        return $this->expires_at !== null
            && now()->diffInDays($this->expires_at, false) < $days;
    }
}
