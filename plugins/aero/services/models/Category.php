<?php namespace Aero\Services\Models;

use Model;

class Category extends Model
{
    use \October\Rain\Database\Traits\Sluggable;
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_services_categories';

    public $fillable = ['name', 'slug', 'icon', 'description', 'sort_order', 'is_active'];

    public $slugs = ['slug' => 'name'];

    public $rules = [
        'name' => 'required',
        'slug' => 'required|alpha_dash',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public $hasMany = [
        'services' => [Service::class, 'key' => 'category_id'],
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
