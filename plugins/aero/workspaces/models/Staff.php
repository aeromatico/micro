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
        'name', 'slug', 'role', 'kind', 'rarity', 'category', 'bio', 'system_prompt',
        'tags', 'capabilities', 'guide', 'connector_id', 'avatar', 'is_active', 'is_orchestrator', 'sort_order',
        'hire_fee', 'task_rates',
    ];

    /** Valores virtuales del formulario; se guardan en afterSave. */
    protected $pendingHireFee = null;
    protected $pendingTaskRates = null;

    public $jsonable = ['tags', 'capabilities', 'guide'];

    // Híbridos (personas) quedan para después: por ahora solo agentes IA.
    public const KINDS = ['ai'];
    public const RARITIES = ['r', 'sr', 'ssr'];

    public $rules = [
        'name'     => 'required|max:120',
        'slug'     => 'required|alpha_dash|max:120',
        'role'     => 'required|max:120',
        'kind'     => 'required|in:ai',
        'rarity'   => 'required|in:r,sr,ssr',
    ];

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

    public function getTaskRatesAttribute(): array
    {
        return $this->taskRateRows()->orderBy('task_type')->get(['task_type', 'fee'])->toArray();
    }

    public function setTaskRatesAttribute($value): void
    {
        $this->pendingTaskRates = is_array($value) ? $value : [];
    }

    /** Conectores del Hub disponibles (si aero/connector está instalado). */
    public function getConnectorIdOptions(): array
    {
        if (!class_exists(\Aero\Connector\Models\Connector::class)) {
            return [];
        }

        return \Aero\Connector\Models\Connector::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
