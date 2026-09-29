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

    public $belongsToMany = [
        'services' => [
            Service::class,
            'table'    => 'aero_services_category_service',
            'key'      => 'category_id',
            'otherKey' => 'service_id',
            'order'    => 'name',
        ],
        'collections' => [
            Collection::class,
            'table'    => 'aero_services_collection_category',
            'key'      => 'category_id',
            'otherKey' => 'collection_id',
            'order'    => 'name',
        ],
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
