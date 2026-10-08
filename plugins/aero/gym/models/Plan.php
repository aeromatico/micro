<?php namespace Aero\Gym\Models;

use Aero\Gym\Classes\TenantOwned;
use Model;

class Plan extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use TenantOwned;

    public $table = 'aero_gym_plans';

    public $fillable = ['tenant_id', 'name', 'description', 'price', 'duration_days', 'classes_per_week', 'shop_product_id', 'is_active'];

    public $rules = [
        'name'          => 'required|string|max:255',
        'price'         => 'required|numeric|min:0',
        'duration_days' => 'required|integer|min:1',
    ];

    public $hasMany = ['memberships' => [Membership::class, 'key' => 'plan_id']];

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }
}
