<?php namespace Aero\Office\Models;

use Aero\Office\Classes\TenantOwned;
use Model;

class Service extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use TenantOwned;

    public $table = 'aero_office_services';

    public $fillable = [
        'tenant_id', 'name', 'category', 'description', 'duration_minutes', 'buffer_minutes', 'price',
        'requires_approval', 'is_active', 'is_public',
    ];

    public $rules = [
        'name'             => 'required|string|max:255',
        'duration_minutes' => 'required|integer|min:5|max:1440',
        'buffer_minutes'   => 'nullable|integer|min:0|max:240',
        'price'            => 'nullable|numeric|min:0',
    ];

    public $belongsToMany = [
        'branches' => [Branch::class, 'table' => 'aero_office_branch_service', 'key' => 'service_id', 'otherKey' => 'branch_id'],
        'workers'  => [Worker::class, 'table' => 'aero_office_service_worker', 'key' => 'service_id', 'otherKey' => 'worker_id'],
    ];

    public function getBranchesOptions(): array
    {
        return Branch::visible()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function getWorkersOptions(): array
    {
        return Worker::visible()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function afterSave(): void
    {
        $this->pruneForeignPivots(['branches' => Branch::class, 'workers' => Worker::class]);
    }

    /** Disponible para el público: activo y no oculto. */
    public function scopePublicly($q)
    {
        return $q->where($this->getTable() . '.is_active', true)->where($this->getTable() . '.is_public', true);
    }

    public function scopeActive($q)
    {
        return $q->where($this->getTable() . '.is_active', true);
    }
}
