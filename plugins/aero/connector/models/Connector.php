<?php namespace Aero\Connector\Models;

use Crypt;
use Illuminate\Contracts\Encryption\DecryptException;
use Model;
use Aero\Connector\Classes\TypeRegistry;

/**
 * Una integración configurada contra un tercero (una API, un modelo de IA, un
 * webhook saliente). El tipo (`type`) determina qué driver la ejecuta y qué
 * campos aplican — ver Aero\Connector\Classes\TypeRegistry.
 *
 * Las credenciales sensibles (api keys, tokens, secretos) NUNCA van en texto
 * plano: se guardan cifradas en `credentials_encrypted` y solo existen en
 * claro en memoria mientras se arma la petición saliente.
 */
class Connector extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_connector_connectors';

    public $fillable = [
        'name', 'type', 'base_url', 'config', 'credentials', 'is_enabled', 'owner_type', 'owner_id',
    ];

    public $rules = [
        'name'     => 'required',
        'type'     => 'required',
        'base_url' => 'nullable|url',
    ];

    protected $hidden = ['credentials_encrypted'];

    public $attributes = [
        'is_enabled' => true,
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'config'     => 'array',
    ];

    public $hasMany = [
        'logs' => [\Aero\Connector\Models\ConnectorLog::class, 'key' => 'connector_id', 'order' => 'id desc'],
    ];

    /**
     * Se usa desde el form (no persistido directo): un array asociativo con
     * las credenciales en claro, cifrado transparente al asignarlo.
     */
    public function setCredentialsAttribute(?array $credentials): void
    {
        $this->attributes['credentials_encrypted'] = $credentials
            ? Crypt::encryptString(json_encode($credentials))
            : null;
    }

    public function getCredentialsAttribute(): array
    {
        if (!$this->credentials_encrypted) {
            return [];
        }

        try {
            return json_decode(Crypt::decryptString($this->credentials_encrypted), true) ?? [];
        }
        catch (DecryptException) {
            // La APP_KEY cambió desde que se guardaron: ya no se pueden recuperar.
            return [];
        }
    }

    public function getTypeLabelAttribute(): string
    {
        return TypeRegistry::find($this->type)['label'] ?? $this->type;
    }

    public function getTypeOptions(): array
    {
        return TypeRegistry::options();
    }

    /**
     * Campos virtuales de solo-edición: el form usa un textarea de JSON
     * plano en vez de un formulario dinámico por tipo (mantiene el plugin
     * agnóstico de qué campos trae cada tipo registrado).
     */
    public function getConfigJsonAttribute(): string
    {
        return $this->config ? json_encode($this->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '';
    }

    public function getCredentialsJsonAttribute(): string
    {
        return $this->credentials ? json_encode($this->credentials, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '';
    }
}
