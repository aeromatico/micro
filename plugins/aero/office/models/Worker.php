<?php namespace Aero\Office\Models;

use Aero\Office\Classes\TenantOwned;
use Model;

/** Profesional. Puede existir sin cuenta de acceso (user_id null). */
class Worker extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use TenantOwned;

    public $table = 'aero_office_workers';

    public $fillable = ['tenant_id', 'user_id', 'name', 'title', 'phone', 'email', 'bio', 'hours', 'is_active', 'is_public'];

    public $jsonable = ['hours'];

    public $rules = [
        'name'  => 'required|string|max:255',
        'email' => 'nullable|email|max:255',
    ];

    public $belongsToMany = [
        'branches' => [Branch::class, 'table' => 'aero_office_branch_worker', 'key' => 'worker_id', 'otherKey' => 'branch_id'],
        'services' => [Service::class, 'table' => 'aero_office_service_worker', 'key' => 'worker_id', 'otherKey' => 'service_id'],
    ];

    public function getBranchesOptions(): array
    {
        return Branch::visible()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function getServicesOptions(): array
    {
        return Service::visible()->orderBy('name')->pluck('name', 'id')->all();
    }

    /** Solo usuarios del backend del mismo tenant (Sites.TenantUser). */
    public function getUserIdOptions(): array
    {
        $opts = ['' => '— Sin cuenta de acceso —'];
        $tenantId = $this->tenant_id ?: \Aero\Office\Classes\CurrentTenant::id();
        if (!$tenantId || !class_exists(\Aero\Sites\Models\TenantUser::class)) {
            return $opts;
        }

        $ids = \Aero\Sites\Models\TenantUser::where('tenant_id', $tenantId)->pluck('user_id');

        return $opts + \Backend\Models\User::whereIn('id', $ids)->orderBy('email')->get()
            ->mapWithKeys(fn ($u) => [$u->id => trim("{$u->first_name} {$u->last_name}") . ' <' . $u->email . '>'])->all();
    }

    public function beforeValidate(): void
    {
        if ($this->user_id && !array_key_exists($this->user_id, $this->getUserIdOptions())) {
            throw new \ApplicationException('El usuario elegido no pertenece a este negocio.');
        }
    }

    public function afterSave(): void
    {
        $this->pruneForeignPivots(['branches' => Branch::class, 'services' => Service::class]);
    }

    public function scopeActive($q)
    {
        return $q->where($this->getTable() . '.is_active', true);
    }

    public function scopePublicly($q)
    {
        return $q->where($this->getTable() . '.is_active', true)->where($this->getTable() . '.is_public', true);
    }
}
