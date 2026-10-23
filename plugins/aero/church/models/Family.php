<?php namespace Aero\Church\Models;

use Model;

/** Núcleo familiar. Privado por tenant. */
class Family extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \Aero\Church\Classes\TenantOwned;

    public $table = 'aero_church_families';

    public static $audited = true;
    public static $auditLabel = 'familia';
    public static $auditType = 'family';

    public $fillable = ['tenant_id', 'name', 'address', 'notes'];

    public $rules = [
        'name' => 'required|string|max:255',
    ];

    public $hasMany = [
        'members' => [Member::class, 'key' => 'family_id'],
    ];

    public function getMembersCountAttribute(): int
    {
        return $this->members()->count();
    }
}
