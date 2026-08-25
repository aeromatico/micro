<?php namespace Aero\Crm\Models;

use Model;

class CrmSettings extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_crm_settings';

    public $fillable = [
        'tenant_id', 'is_enabled',
        'collections_enabled', 'reminder_interval_days', 'reminder_message_template',
    ];

    public $rules = [
        'tenant_id' => 'required|exists:aero_sites_tenants,id|unique:aero_crm_settings,tenant_id',
    ];

    public $belongsTo = [
        'tenant' => [\Aero\Sites\Models\Tenant::class],
    ];

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
