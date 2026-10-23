<?php namespace Aero\Church\Models;

use Model;

/** Sede de la iglesia. Privada por tenant. */
class Branch extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \Aero\Church\Classes\TenantOwned;

    public $table = 'aero_church_branches';

    public $fillable = ['tenant_id', 'name', 'city', 'address', 'phone', 'is_main', 'is_active'];

    public $rules = [
        'name' => 'required|string|max:255',
    ];

    public $hasMany = [
        'members' => [Member::class, 'key' => 'branch_id'],
    ];
}
