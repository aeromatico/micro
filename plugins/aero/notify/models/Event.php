<?php namespace Aero\Notify\Models;

use Model;

/**
 * Evento notificable del catálogo. Es global (no tiene tenant): el superadmin
 * declara qué puede notificarse y con qué variables; los tenants solo deciden
 * a quién y por dónde, vía Rule.
 *
 * Ojo al usar la facade de eventos de Laravel dentro del plugin: esta clase se
 * llama Event, así que hay que escribir \Event::fire(...) con la barra.
 */
class Event extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\Purgeable;

    public $table = 'aero_notify_events';

    /**
     * Campos virtuales del formulario: el editor de código trabaja con texto,
     * pero en la base los dos campos son JSON. Se purgan para que no intenten
     * guardarse como columnas.
     */
    protected $purgeable = ['variables_schema_json', 'sample_context_json'];

    protected $guarded = [];

    protected $jsonable = [
        'variables_schema', 'sample_context', 'default_channels', 'default_audiences',
    ];

    protected $casts = [
        'priority'         => 'integer',
        'is_transactional' => 'boolean',
        'is_active'        => 'boolean',
        'is_system'        => 'boolean',
    ];

    public $rules = [
        'code'     => 'required|max:100|regex:/^[a-z][a-z0-9_]*(\.[a-z0-9_]+)+$/',
        'name'     => 'required|max:150',
        'category' => 'required|max:50',
        'priority' => 'required|integer|between:1,9',
    ];

    public $customMessages = [
        'code.regex' => 'El código debe tener la forma grupo.recurso.accion, en minúsculas.',
    ];

    public $attributes = [
        'category'         => 'general',
        'priority'         => 5,
        'is_transactional' => true,
        'is_active'        => true,
        'is_system'        => false,
    ];

    public $hasMany = [
        'rules'     => [Rule::class],
        'templates' => [Template::class],
    ];

    public function beforeValidate(): void
    {
        // El code se usa como clave en todo el sistema; nunca con mayúsculas
        // ni espacios de más.
        $this->code = strtolower(trim((string) $this->code));
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeSourcePlugin($query, string $plugin)
    {
        return $query->where('source_plugin', $plugin);
    }

    public function getCategoryOptions(): array
    {
        return [
            'billing'   => 'Facturación y cobranza',
            'orders'    => 'Pedidos',
            'sales'     => 'Ventas y CRM',
            'support'   => 'Soporte y mensajería',
            'system'    => 'Sistema',
            'security'  => 'Seguridad',
            'marketing' => 'Marketing',
            'general'   => 'General',
        ];
    }

    /**
     * Nombres de las variables declaradas como obligatorias. Notify::fire() las
     * exige en el contexto; a diferencia del context_vars decorativo del
     * sistema que tomamos como referencia, esto sí se valida.
     */
    public function requiredVariables(): array
    {
        $required = [];

        foreach ((array) $this->variables_schema as $name => $spec) {
            if (!empty($spec['required'])) {
                $required[] = $name;
            }
        }

        return $required;
    }

    public function variableNames(): array
    {
        return array_keys((array) $this->variables_schema);
    }

    //
    // Puente entre el editor de código (texto) y las columnas JSON.
    //

    public function getVariablesSchemaJsonAttribute(): string
    {
        return $this->encodeJsonField($this->variables_schema);
    }

    public function setVariablesSchemaJsonAttribute($value): void
    {
        $this->variables_schema = $this->decodeJsonField($value, 'Contrato de variables');
    }

    public function getSampleContextJsonAttribute(): string
    {
        return $this->encodeJsonField($this->sample_context);
    }

    public function setSampleContextJsonAttribute($value): void
    {
        $this->sample_context = $this->decodeJsonField($value, 'Contexto de ejemplo');
    }

    protected function encodeJsonField($value): string
    {
        $value = (array) $value;

        if (!$value) {
            return '{}';
        }

        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Un JSON mal formado se rechaza al guardar en vez de vaciar el campo en
     * silencio: perder el contrato de variables sin avisar es peor que un error.
     */
    protected function decodeJsonField($value, string $label): array
    {
        if (is_array($value)) {
            return $value;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            throw new \ValidationException([
                'variables_schema_json' => "{$label}: el JSON no es válido (" . json_last_error_msg() . ').',
            ]);
        }

        return $decoded;
    }
}
