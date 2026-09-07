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
    use \October\Rain\Database\Traits\Purgeable;

    public $table = 'aero_connector_connectors';

    public $fillable = [
        'name', 'type', 'provider_hint', 'base_url', 'port', 'config', 'credentials', 'is_enabled', 'owner_type', 'owner_id',
        'credit_cost', 'credit_type_id',
    ];

    /**
     * Campos virtuales del form que el binding automático setea como
     * atributos crudos del modelo (no tienen columna propia — `api_key`/
     * `secret` se traducen a `credentials` en Connectors::formBeforeSave(),
     * `config_json`/`credentials_json` a `config`/`credentials`). Sin
     * purgarlos, Eloquent intenta insertarlos como columnas reales y explota.
     */
    protected $purgeable = ['api_key', 'secret', 'ai_model', 'config_json', 'credentials_json'];

    public $rules = [
        'name'     => 'required',
        'base_url' => 'nullable|url',
    ];

    /**
     * Hosts conocidos de proveedores de IA, para que `resolveType()` adivine
     * el tipo sin que el usuario tenga que elegirlo. Ampliar esta lista a
     * mano cuando se sume un proveedor con dominio propio no reconocido acá
     * (mientras tanto, el override manual `provider_hint` cubre el caso).
     */
    protected const AI_HOSTS = ['openai.com', 'openrouter.ai', 'opencode.ai', 'kilo.ai', 'deepseek.com'];

    /**
     * Presets de `provider_hint` que además de fijar el `type` autocompletan
     * `base_url` cuando está vacía — así el usuario no tiene que buscar el
     * endpoint de cada proveedor a mano.
     */
    protected const PROVIDER_DEFAULT_BASE_URLS = [
        'openrouter'   => 'https://openrouter.ai/api/v1',
        'opencode_zen' => 'https://opencode.ai/zen/v1',
        'kilocode'     => 'https://api.kilo.ai/api/gateway',
        'openai'       => 'https://api.openai.com/v1',
        'anthropic'    => 'https://api.anthropic.com',
        'deepseek'     => 'https://api.deepseek.com',
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

    /**
     * `type` ya no lo elige el usuario en el form: se recalcula siempre antes
     * de validar, así que el resto del sistema (TypeRegistry, ConnectorClient,
     * los drivers, y los filtros por category en Aero.Chatbots) sigue viendo
     * los mismos valores de siempre sin que nadie más tenga que cambiar.
     */
    public function beforeValidate()
    {
        // Los inputs numéricos nullable del form (port, credit_cost,
        // credit_type_id) mandan '' cuando quedan vacíos — sin castearlos
        // a null, MySQL los rechaza como entero inválido.
        foreach (['port', 'credit_cost', 'credit_type_id'] as $field) {
            if ($this->{$field} === '') {
                $this->{$field} = null;
            }
        }

        if (!$this->base_url && isset(static::PROVIDER_DEFAULT_BASE_URLS[$this->provider_hint])) {
            $this->base_url = static::PROVIDER_DEFAULT_BASE_URLS[$this->provider_hint];
        }

        $this->type = $this->resolveType();
    }

    protected function resolveType(): string
    {
        if ($this->provider_hint === 'anthropic') {
            return 'ai_anthropic';
        }
        if (in_array($this->provider_hint, ['openrouter', 'opencode_zen', 'kilocode', 'openai', 'deepseek', 'ai_custom'], true)) {
            return 'ai_openai_compatible';
        }
        if ($this->provider_hint === 'http') {
            return 'http';
        }

        $host = (string) parse_url((string) $this->base_url, PHP_URL_HOST);

        if (str_contains($host, 'anthropic.com')) {
            return 'ai_anthropic';
        }

        foreach (static::AI_HOSTS as $aiHost) {
            if ($host && str_contains($host, $aiHost)) {
                return 'ai_openai_compatible';
            }
        }

        // Default seguro: HTTP genérico. No se asume IA solo porque haya una
        // api_key cargada — casi cualquier API REST no-IA también usa una.
        return 'http';
    }

    /**
     * `base_url` tal cual lo escribió el usuario, con `port` insertado si
     * está seteado (para proveedores propios en un puerto no estándar, ej.
     * Ollama/LM Studio). Los drivers usan esto en vez de `base_url` directo
     * para armar la petición saliente.
     */
    public function resolvedBaseUrl(): ?string
    {
        if (!$this->base_url || !$this->port) {
            return $this->base_url;
        }

        $parts = parse_url($this->base_url);
        if (!$parts || empty($parts['host'])) {
            return $this->base_url;
        }

        $scheme = $parts['scheme'] ?? 'https';
        $path = $parts['path'] ?? '';
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';

        return "{$scheme}://{$parts['host']}:{$this->port}{$path}{$query}";
    }

    /**
     * Se usan para poblar los inputs de texto "API Key"/"Secret" del form
     * (reemplazan al textarea JSON como flujo principal de credenciales).
     */
    public function getApiKeyAttribute(): ?string
    {
        return $this->credentials['api_key'] ?? null;
    }

    public function getSecretAttribute(): ?string
    {
        return $this->credentials['secret'] ?? null;
    }

    /**
     * Modelo por defecto de este connector (solo aplica a los de categoría
     * IA). Un bot de Aero.Chatbots puede pedir uno distinto por llamada —
     * ver `payload['model']` en AiOpenAiCompatibleDriver/AiAnthropicDriver —
     * este es el que se usa si no lo hace.
     */
    public function getAiModelAttribute(): ?string
    {
        return $this->config['model'] ?? null;
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
     * Vacío si Aero.Credits no está instalado: el campo simplemente no
     * tiene opciones y `credit_cost` queda sin efecto (ver
     * Aero\Credits\Plugin::bootConnectorIntegration()).
     */
    public function getCreditTypeIdOptions(): array
    {
        if (!class_exists(\Aero\Credits\Models\CreditType::class)) {
            return [];
        }

        return \Aero\Credits\Models\CreditType::active()->pluck('label', 'id')->all();
    }

    /**
     * Solo la config "extra" (todo lo que no sea `model`, que ya tiene su
     * propio input "Modelo IA" en el form) — igual que `credentials_json`,
     * queda como escotilla de escape avanzada.
     */
    public function getConfigJsonAttribute(): string
    {
        // El cast 'array' de Eloquent devuelve null (no []) cuando la
        // columna todavía no tiene valor, ej. un Connector nuevo sin guardar.
        $advanced = array_diff_key((array) $this->config, array_flip(['model']));

        return $advanced ? json_encode($advanced, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '';
    }

    /**
     * Solo las credenciales "extra" (todo lo que no sea api_key/secret, que
     * ya tienen su propio input de texto en el form) — este textarea queda
     * como escotilla de escape avanzada, no como flujo principal.
     */
    public function getCredentialsJsonAttribute(): string
    {
        $advanced = array_diff_key($this->credentials, array_flip(['api_key', 'secret']));

        return $advanced ? json_encode($advanced, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '';
    }
}
