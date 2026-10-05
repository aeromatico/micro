<?php namespace Aero\Workspaces\Models;

use Model;

/** Contratación de un staff por un tenant. Un tenant contrata a cada agente una sola vez. */
class Hire extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_workspaces_hires';

    public $fillable = ['tenant_id', 'staff_id', 'fee_charged', 'hired_at'];

    protected $dates = ['hired_at'];

    public $rules = [
        'tenant_id'   => 'required|integer',
        'staff_id'    => 'required|integer',
        'fee_charged' => 'required|numeric|min:0',
    ];

    public $belongsTo = [
        'staff' => [Staff::class, 'key' => 'staff_id'],
    ];

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
