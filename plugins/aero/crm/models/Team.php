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

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
