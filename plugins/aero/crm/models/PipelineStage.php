<?php namespace Aero\Crm\Models;

use Model;

class PipelineStage extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_crm_pipeline_stages';

    public $fillable = ['tenant_id', 'pipeline_id', 'name', 'sort_order', 'color', 'is_won', 'is_lost'];

    public $rules = [
        'tenant_id'   => 'required|exists:aero_sites_tenants,id',
        'pipeline_id' => 'required|exists:aero_crm_pipelines,id',
        'name'        => 'required|max:255',
    ];

    public $belongsTo = [
        'tenant'   => [\Aero\Sites\Models\Tenant::class],
        'pipeline' => [Pipeline::class],
    ];

    public $hasMany = [
        'deals' => [Deal::class, 'key' => 'stage_id'],
    ];

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
