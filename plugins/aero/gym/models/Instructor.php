<?php namespace Aero\Gym\Models;

use Aero\Gym\Classes\TenantOwned;
use Model;

class Instructor extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use TenantOwned;

    public $table = 'aero_gym_instructors';

    public $fillable = ['tenant_id', 'user_id', 'name', 'phone', 'email', 'bio', 'is_active'];

    public $rules = ['name' => 'required|string|max:255'];

    public $hasMany = ['sessions' => [ClassSession::class, 'key' => 'instructor_id']];
}
