<?php namespace Aero\Realestate\Models;

use Aero\Realestate\Classes\CurrentTenant;
use Model;

/** Característica de un inmueble (piscina, jardín, ascensor, seguridad…). */
class Feature extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \Aero\Realestate\Classes\TenantOwned;

    public $table = 'aero_realestate_features';

    public $fillable = ['tenant_id', 'name', 'category', 'icon'];

    public $rules = ['name' => 'required|string|max:255'];

    public $belongsToMany = [
        'properties' => [Property::class, 'table' => 'aero_realestate_feature_property', 'key' => 'feature_id', 'otherKey' => 'property_id'],
    ];

    public function scopeAvailable($query)
    {
        $id = CurrentTenant::isAdmin() ? null : CurrentTenant::id();

        return CurrentTenant::isAdmin()
            ? $query
            : $query->where(fn ($q) => $q->whereNull('tenant_id')->when($id, fn ($w) => $w->orWhere('tenant_id', $id)));
    }
}
