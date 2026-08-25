<?php namespace Aero\Crm\Models;

use Model;

class Deal extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_crm_deals';

    public $fillable = [
        'tenant_id', 'pipeline_id', 'stage_id', 'contact_id', 'company_id', 'title',
        'value', 'currency', 'owner_id', 'team_id', 'expected_close_date',
        'status', 'sort_order', 'closed_at',
    ];

    public $rules = [
        'tenant_id'   => 'required|exists:aero_sites_tenants,id',
        'pipeline_id' => 'required|exists:aero_crm_pipelines,id',
        'stage_id'    => 'required|exists:aero_crm_pipeline_stages,id',
        'title'       => 'required|max:255',
    ];

    protected $dates = ['expected_close_date', 'closed_at'];

    public $belongsTo = [
        'tenant'   => [\Aero\Sites\Models\Tenant::class],
        'pipeline' => [Pipeline::class],
        'stage'    => [PipelineStage::class],
        'contact'  => [Contact::class],
        'company'  => [Company::class],
        'owner'    => [\Backend\Models\User::class],
        'team'     => [Team::class],
    ];

    public $hasMany = [
        'activities' => [Activity::class, 'key' => 'related_id', 'conditions' => "related_type = 'Aero\\\\Crm\\\\Models\\\\Deal'"],
    ];

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    /**
     * Opciones para el filtro de lista "Etapa" (config_filter.yaml). No se
     * limita al tenant actual porque el widget de filtro no tiene acceso a
     * ese contexto aquí — la lista en sí ya está tenant-scoped vía
     * listExtendQuery(), así que esto solo puebla el dropdown.
     */
    public function getStageIdOptions()
    {
        return PipelineStage::orderBy('sort_order')->pluck('name', 'id')->toArray();
    }

    public function beforeSave()
    {
        if (in_array($this->status, ['won', 'lost'], true) && !$this->closed_at) {
            $this->closed_at = now();
        }
    }
}
