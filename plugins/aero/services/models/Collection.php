<?php namespace Aero\Services\Models;

use Model;

/**
 * Agrupa servicios afines para presentarlos juntos (p. ej. "Plugins especializados por rubro").
 * Jerarquía: Colección → Categorías → Servicios, ambas relaciones múltiples.
 */
class Collection extends Model
{
    use \October\Rain\Database\Traits\Sluggable;
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_services_collections';

    public $fillable = ['name', 'slug', 'icon', 'summary', 'description', 'sort_order', 'is_active'];

    public $slugs = ['slug' => 'name'];

    public $rules = [
        'name' => 'required',
        'slug' => 'required|alpha_dash',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public $belongsToMany = [
        'categories' => [
            Category::class,
            'table'    => 'aero_services_collection_category',
            'key'      => 'collection_id',
            'otherKey' => 'category_id',
            'order'    => 'sort_order',
        ],
    ];

    /** Servicios de la colección: los de todas sus categorías, sin repetir. */
    public function getServicesAttribute()
    {
        return $this->categories()->with('services')->get()
            ->flatMap->services
            ->unique('id')
            ->sortBy([['sort_order', 'asc'], ['name', 'asc']])
            ->values();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
