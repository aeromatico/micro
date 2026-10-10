<?php namespace Aero\Office\Models;

use Aero\Office\Classes\TenantOwned;
use Model;

class Branch extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\Nullable;
    use TenantOwned;

    public $table = 'aero_office_branches';

    public $nullable = ['lat', 'lng'];

    public $fillable = ['tenant_id', 'name', 'address', 'phone', 'lat', 'lng', 'hours', 'is_active'];

    public $jsonable = ['hours'];

    public $rules = ['name' => 'required|string|max:255'];

    public $belongsToMany = [
        'services' => [Service::class, 'table' => 'aero_office_branch_service', 'key' => 'branch_id', 'otherKey' => 'service_id'],
        'workers'  => [Worker::class, 'table' => 'aero_office_branch_worker', 'key' => 'branch_id', 'otherKey' => 'worker_id'],
    ];

    public function getServicesOptions(): array
    {
        return Service::visible()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function getWorkersOptions(): array
    {
        return Worker::visible()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function afterSave(): void
    {
        $this->pruneForeignPivots(['services' => Service::class, 'workers' => Worker::class]);
    }

    public function scopeActive($q)
    {
        return $q->where($this->getTable() . '.is_active', true);
    }
}
