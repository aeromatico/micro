<?php namespace Aero\Workspaces\Models;

use Model;

/**
 * Empleado virtual del catálogo. Solo el superadmin lo crea y edita (incluido
 * su prompt de sistema). Todo staff tiene su fila de tarifa desde que se crea.
 */
class Staff extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_workspaces_staff';

    public $fillable = [
        'name', 'slug', 'role', 'kind', 'rarity', 'quality', 'status', 'fee_mode', 'credit_type', 'plan_codes', 'category', 'bio', 'system_prompt',
        'tags', 'capabilities', 'guide', 'connector_id', 'model_code', 'fallback_model_code', 'avatar', 'is_active', 'is_orchestrator', 'sort_order', 'base_contracts',
        'hire_fee', 'task_rates',
    ];

    /** Valores virtuales del formulario; se guardan en afterSave. */
    protected $pendingHireFee = null;
    protected $pendingTaskRates = null;

    public $jsonable = ['tags', 'capabilities', 'guide', 'plan_codes'];

    // Híbridos (personas) quedan para después: por ahora solo agentes IA.
    public const KINDS = ['ai'];
    public const RARITIES = ['r', 'sr', 'ssr'];

    public const TRAINING = 'training';
    public const ONLINE = 'online';
    public const UNAVAILABLE = 'unavailable';

    public const STATUSES = [
        self::TRAINING    => 'En entrenamiento',
        self::ONLINE      => 'Online',
        self::UNAVAILABLE => 'No disponible',
    ];

    public const FEE_MODES = ['free' => 'Gratis', 'credits' => 'En créditos'];

    public $rules = [
        'name'     => 'required|max:120',
        'fallback_model_code' => 'nullable|max:40',
        'slug'     => 'required|alpha_dash|max:120',
        'role'     => 'required|max:120',
        'kind'     => 'required|in:ai',
        'rarity'   => 'nullable|in:r,sr,ssr',
        'quality'  => 'nullable|integer|between:1,5',
        'status'   => 'nullable|in:training,online,unavailable',
        'fee_mode' => 'nullable|in:free,credits',
        'credit_type' => 'nullable|max:40',
    ];

    /**
     * ¿Ya existen las columnas del catálogo dinámico (1.17.0)? Las cargas iniciales antiguas
     * crean agentes con este modelo antes de que se agreguen; entonces no se tocan.
     */
    protected static function hasCatalogColumns(): bool
    {
        // Sin caché: una carga inicial antigua corre antes de la migración 1.17.0 (y en pruebas la BD se recrea).
        return \Schema::hasColumn('aero_workspaces_staff', 'status');
    }

    public $hasOne = [
        'rate' => [StaffRate::class, 'key' => 'staff_id'],
    ];

    // Nombre distinto de `task_rates` (el accesor del formulario): con el mismo nombre October lo tapa.
    public $hasMany = [
        'taskRateRows' => [TaskRate::class, 'key' => 'staff_id'],
    ];

    public $belongsTo = [];

    public $belongsToMany = [
        'skills' => [
            Skill::class,
            'table' => 'aero_workspaces_staff_skill',
            'key' => 'staff_id',
            'otherKey' => 'skill_id',
        ],
    ];

    /** El orquestador activo (a lo sumo uno). */
    public static function orchestrator(): ?self
    {
        return static::where('is_orchestrator', true)->where('is_active', true)->first();
    }

    /** `is_active` (lo que usa el resto del sistema) es «estado = Online»; se mantienen sincronizados en ambos sentidos. */
    public function beforeSave(): void
    {
        if (!static::hasCatalogColumns()) {
            unset($this->attributes['quality'], $this->attributes['status'], $this->attributes['fee_mode'], $this->attributes['credit_type'], $this->attributes['plan_codes']);

            return;
        }

        $this->quality = $this->quality ?: 3;
        $this->status = $this->status ?: self::ONLINE;
        $this->fee_mode = $this->fee_mode ?: 'free';

        if ($this->is_orchestrator) {
            $this->status = self::ONLINE;
        }
        elseif (!$this->exists) {
            // Alta: si se pidió explícitamente inactivo y el estado quedó en el valor por defecto, es «No disponible».
            if ($this->is_active !== null && !$this->is_active && $this->status === self::ONLINE) {
                $this->status = self::UNAVAILABLE;
            }
        }
        elseif ($this->isDirty('status')) {
            // el estado manda
        }
        elseif ($this->isDirty('is_active')) {
            $this->status = $this->is_active ? self::ONLINE : ($this->status === self::TRAINING ? self::TRAINING : self::UNAVAILABLE);
        }

        $this->is_active = $this->status === self::ONLINE;

        if ($this->fee_mode === 'free') {
            $this->credit_type = $this->credit_type ?: null;
        }
    }

    public function afterSave(): void
    {
        // Un solo orquestador: marcar uno desmarca a los demás.
        if ($this->is_orchestrator) {
            static::where('id', '!=', $this->id)->where('is_orchestrator', true)->update(['is_orchestrator' => false]);
        }

        // Ningún agente queda sin tarifa: si no se define, la contratación es gratis.
        // Se consulta la tabla directo: la relación `rate` pudo quedar cacheada como null antes de existir.
        $current = StaffRate::where('staff_id', $this->id)->value('hire_fee');

        StaffRate::updateOrCreate(
            ['staff_id' => $this->id],
            ['hire_fee' => max(0, (float) ($this->pendingHireFee ?? $current ?? 0))]
        );
        $this->pendingHireFee = null;
        $this->unsetRelation('rate');
        $this->unsetRelation('taskRateRows');

        if (is_array($this->pendingTaskRates)) {
            TaskRate::where('staff_id', $this->id)->delete();

            foreach ($this->pendingTaskRates as $row) {
                $type = trim((string) ($row['task_type'] ?? ''));

                if ($type === '') {
                    continue;
                }

                TaskRate::create([
                    'staff_id' => $this->id,
                    'task_type' => $type,
                    'fee' => max(0, (float) ($row['fee'] ?? 0)),
                ]);
            }

            $this->pendingTaskRates = null;
        }
    }

    /** Un taglist vacío llega como '' y la columna JSON lo rechaza (CHECK json_valid): se guarda como []. */
    public function setTagsAttribute($value): void
    {
        $this->attributes['tags'] = json_encode($this->normalizeJsonList($value), JSON_UNESCAPED_UNICODE);
    }

    public function setGuideAttribute($value): void
    {
        $this->attributes['guide'] = json_encode($this->normalizeJsonList($value), JSON_UNESCAPED_UNICODE);
    }

    public function setCapabilitiesAttribute($value): void
    {
        $this->attributes['capabilities'] = json_encode(is_array($value) ? $value : [], JSON_UNESCAPED_UNICODE);
    }

    protected function normalizeJsonList($value): array
    {
        if (is_string($value)) {
            $value = trim($value) === '' ? [] : (json_decode($value, true) ?? array_map('trim', explode(',', $value)));
        }

        return array_values(array_filter((array) $value, fn ($v) => $v !== '' && $v !== null));
    }

    public function getHireFeeAttribute(): float
    {
        return (float) (StaffRate::where('staff_id', $this->id)->value('hire_fee') ?? 0);
    }

    public function setHireFeeAttribute($value): void
    {
        $this->pendingHireFee = (float) $value;
    }

    /** Tarifas rápidas para la lista del catálogo. */
    public function getEncargoFeeAttribute(): float
    {
        return (float) (TaskRate::where('staff_id', $this->id)->where('task_type', 'encargo')->value('fee') ?? 0);
    }

    public function getChatFeeAttribute(): float
    {
        return (float) (TaskRate::where('staff_id', $this->id)->where('task_type', 'chat_turn')->value('fee') ?? 0);
    }

    public function getTaskRatesAttribute(): array
    {
        return $this->taskRateRows()->orderBy('task_type')->get(['task_type', 'fee'])->toArray();
    }

    public function setTaskRatesAttribute($value): void
    {
        $this->pendingTaskRates = is_array($value) ? $value : [];
    }

    /** Modelo de respaldo: las mismas opciones, pidiendo uno distinto al principal. */
    public function getFallbackModelCodeOptions(): array
    {
        return array_merge(['' => '(sin respaldo)'], array_slice($this->getModelCodeOptions(), 1, null, true));
    }

    /** Modelos especializados de Ajustes con los que puede conversar un agente (código y texto). */
    public function getModelCodeOptions(): array
    {
        $options = ['' => '(el modelo por defecto de Ajustes)'];

        foreach (Settings::models() as $code => $profile) {
            if (in_array($profile['kind'], Settings::CHAT_KINDS, true)) {
                $options[$code] = $profile['label'] . ' — ' . $profile['model'];
            }
        }

        return $options;
    }

    /** Conectores del Hub disponibles (si aero/connector está instalado). */
    public function getConnectorIdOptions(): array
    {
        if (!class_exists(\Aero\Connector\Models\Connector::class)) {
            return [];
        }

        return \Aero\Connector\Models\Connector::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    /** Online: se puede contratar y trabajar con él. */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** Lo que muestra el Mercado: online y en entrenamiento (estos últimos sin poder contratarse). */
    public function scopeListed($query)
    {
        return $query->whereIn('status', [self::ONLINE, self::TRAINING]);
    }

    public function getStatusOptions(): array
    {
        return self::STATUSES;
    }

    public function getFeeModeOptions(): array
    {
        return self::FEE_MODES;
    }

    public function getQualityOptions(): array
    {
        return [1 => '★☆☆☆☆  1 · Básico', 2 => '★★☆☆☆  2 · Correcto', 3 => '★★★☆☆  3 · Bueno', 4 => '★★★★☆  4 · Muy bueno', 5 => '★★★★★  5 · Excelente'];
    }

    /** Categorías dinámicas del catálogo (Staff → Categorías). */
    public function getCategoryOptions(): array
    {
        $options = Category::options();

        // Una categoría ya asignada sigue siendo elegible aunque la hayan ocultado.
        if ($this->category && !isset($options[$this->category])) {
            $options[$this->category] = ucfirst($this->category) . ' (oculta)';
        }

        return $options;
    }

    /** Tipos de crédito de Aero.Credits (código => nombre). */
    public function getCreditTypeOptions(): array
    {
        if (!class_exists(\Aero\Credits\Models\CreditType::class)) {
            return [];
        }

        return \Aero\Credits\Models\CreditType::active()->orderBy('sort_order')->pluck('label', 'code')->all();
    }

    /** Planes de la plataforma a los que corresponde el agente (código => nombre). */
    public function getPlanCodesOptions(): array
    {
        if (!class_exists(\Aero\Sites\Models\Plan::class)) {
            return [];
        }

        return \Aero\Sites\Models\Plan::where('is_active', true)->orderBy('sort_order')->pluck('name', 'code')->all();
    }
}
