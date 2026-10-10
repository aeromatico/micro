<?php namespace Aero\Office\Models;

use Aero\Office\Classes\TenantOwned;
use Model;

/** Vacaciones, descansos, ausencias y feriados (worker_id null = todo el negocio). */
class TimeOff extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\Nullable;
    use TenantOwned;

    public $table = 'aero_office_time_offs';

    public $nullable = ['worker_id', 'branch_id', 'reason'];

    public $fillable = ['tenant_id', 'worker_id', 'branch_id', 'type', 'starts_at', 'ends_at', 'reason'];

    protected $dates = ['starts_at', 'ends_at'];

    public $rules = [
        'type'      => 'required|in:holiday,vacation,break,absence',
        'starts_at' => 'required|date',
        'ends_at'   => 'required|date|after:starts_at',
    ];

    public $belongsTo = [
        'worker' => [Worker::class, 'key' => 'worker_id'],
        'branch' => [Branch::class, 'key' => 'branch_id'],
    ];

    public function getTypeOptions(): array
    {
        return ['holiday' => 'Feriado / cierre', 'vacation' => 'Vacaciones', 'break' => 'Descanso', 'absence' => 'Ausencia'];
    }

    public function getWorkerIdOptions(): array
    {
        return ['' => '— Todo el negocio —'] + Worker::visible()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function getBranchIdOptions(): array
    {
        return ['' => '— Todas las sucursales —'] + Branch::visible()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function beforeValidate(): void
    {
        $this->assertReferencesVisible(['worker_id' => Worker::class, 'branch_id' => Branch::class]);
        if (empty($this->tenant_id) && $this->worker_id) {
            $this->tenant_id = Worker::whereKey($this->worker_id)->value('tenant_id');
        }
    }
}
