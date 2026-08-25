<?php namespace Aero\Crm\Models;

use Model;

class Activity extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_crm_activities';

    public $fillable = [
        'tenant_id', 'related_type', 'related_id', 'type', 'subject',
        'description', 'due_at', 'completed_at', 'owner_id',
    ];

    public $rules = [
        'tenant_id'    => 'required|exists:aero_sites_tenants,id',
        'related_type' => 'required',
        'related_id'   => 'required|integer',
        'type'         => 'required|in:call,email,whatsapp,meeting,note,task',
        'subject'      => 'required|max:255',
    ];

    protected $dates = ['due_at', 'completed_at'];

    public $belongsTo = [
        'tenant' => [\Aero\Sites\Models\Tenant::class],
        'owner'  => [\Backend\Models\User::class],
    ];

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopePending($query)
    {
        return $query->whereNull('completed_at');
    }

    public function related()
    {
        $class = $this->related_type;
        return class_exists($class) ? $class::find($this->related_id) : null;
    }
}
