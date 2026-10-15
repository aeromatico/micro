<?php namespace Aero\School\Models;

use Aero\School\Classes\TenantOwned;
use Model;

/** Apoderado. user_id enlaza con RainLab.User para entrar al portal. */
class Guardian extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use TenantOwned;

    public $table = 'aero_school_guardians';

    public $fillable = ['tenant_id', 'user_id', 'name', 'document', 'phone', 'email', 'address', 'occupation', 'notes'];

    public $rules = ['name' => 'required|string|max:255', 'email' => 'nullable|email'];

    public $belongsToMany = [
        'students' => [Student::class, 'table' => 'aero_school_student_guardian', 'key' => 'guardian_id', 'otherKey' => 'student_id',
            'pivot' => ['relationship', 'is_primary', 'is_financial']],
    ];

    public function getStudentsCountAttribute(): int
    {
        return $this->students()->count();
    }
}
