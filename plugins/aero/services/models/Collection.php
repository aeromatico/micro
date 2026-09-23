<?php namespace Aero\Services\Models;

use Model;

/**
 * Agrupa servicios afines para presentarlos juntos (p. ej. "Plugins especializados por rubro").
 * A diferencia de Category (una sola por servicio), un servicio puede estar en varias colecciones.
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
        'services' => [
            Service::class,
            'table'    => 'aero_services_collection_service',
            'key'      => 'collection_id',
            'otherKey' => 'service_id',
            'order'    => 'sort_order',
        ],
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
