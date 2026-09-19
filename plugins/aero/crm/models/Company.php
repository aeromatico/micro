<?php namespace Aero\Crm\Models;

use Model;

class Company extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \Aero\Crm\Classes\HasTenantOwnerOptions;

    public $table = 'aero_crm_companies';

    public $fillable = ['tenant_id', 'name', 'website', 'industry', 'phone', 'address', 'owner_id', 'social_links'];

    public $jsonable = ['social_links'];

    public $rules = [
        'tenant_id' => 'required|exists:aero_sites_tenants,id',
        'name'      => 'required|max:255',
    ];

    public $belongsTo = [
        'tenant' => [\Aero\Sites\Models\Tenant::class],
        'owner'  => [\Backend\Models\User::class],
    ];

    public $hasMany = [
        'contacts' => [Contact::class],
        'deals'    => [Deal::class],
    ];

    /**
     * Opciones del campo "Empresa" en otros formularios (el Relation widget
     * pasa el modelo en edición): solo las empresas de su mismo tenant.
     */
    public function scopeBelongingToTenant($query, $model)
    {
        return $model && $model->tenant_id
            ? $query->where('tenant_id', $model->tenant_id)
            : $query;
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
