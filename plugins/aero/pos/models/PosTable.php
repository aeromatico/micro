<?php namespace Aero\Pos\Models;

use Model;

class PosTable extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_pos_tables';

    public $fillable = ['tenant_id', 'zone', 'name', 'seats', 'sort_order', 'is_active'];

    public $rules = [
        'tenant_id' => 'required|exists:aero_sites_tenants,id',
        'name'      => 'required|max:30',
        'seats'     => 'nullable|integer|min:1|max:99',
    ];

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
