<?php namespace Aero\Crm\Models;

use Model;

class Team extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_crm_teams';

    public $fillable = ['tenant_id', 'name'];

    public $rules = [
        'tenant_id' => 'required|exists:aero_sites_tenants,id',
        'name'      => 'required|max:255',
    ];

    public $belongsTo = [
        'tenant' => [\Aero\Sites\Models\Tenant::class],
    ];

    public $belongsToMany = [
        'members' => [
            \Backend\Models\User::class,
            'table' => 'aero_crm_team_members',
            'key'   => 'team_id',
            'otherKey' => 'user_id',
        ],
    ];

    public $hasMany = [
        'deals' => [Deal::class],
    ];

    /**
     * Opciones del campo "Miembros": solo usuarios del tenant del equipo (o del
     * tenant actual en un equipo nuevo); los miembros ya guardados se conservan.
     */
    public function getMembersOptions(): array
    {
        return \Aero\Crm\Classes\TenantUsers::options(
            $this->tenant_id ? (int) $this->tenant_id : \Aero\Crm\Classes\TenantUsers::currentTenantId(),
            $this->exists ? $this->members()->pluck('backend_users.id')->all() : []
        );
    }

    /**
     * Opciones de relación en formularios de otros modelos: solo las del
     * tenant del registro en edición (el Relation widget pasa el modelo).
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
