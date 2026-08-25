<?php namespace Aero\Crm\Models;

use Model;

class Pipeline extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_crm_pipelines';

    public $fillable = ['tenant_id', 'name'];

    public $rules = [
        'tenant_id' => 'required|exists:aero_sites_tenants,id|unique:aero_crm_pipelines,tenant_id',
        'name'      => 'required|max:255',
    ];

    public $belongsTo = [
        'tenant' => [\Aero\Sites\Models\Tenant::class],
    ];

    public $hasMany = [
        'stages' => [PipelineStage::class, 'order' => 'sort_order asc'],
        'deals'  => [Deal::class],
    ];

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Un solo pipeline por tenant (decisión v1) — crea uno con etapas por
     * defecto la primera vez que un tenant activa el CRM.
     */
    public static function seedDefaultForTenant(int $tenantId): self
    {
        $pipeline = static::firstOrCreate(['tenant_id' => $tenantId], ['name' => 'Ventas']);

        if ($pipeline->stages()->count() === 0) {
            $defaults = [
                ['name' => 'Nuevo', 'color' => '#64748b'],
                ['name' => 'Contactado', 'color' => '#3b82f6'],
                ['name' => 'Propuesta', 'color' => '#f59e0b'],
                ['name' => 'Ganado', 'color' => '#22c55e', 'is_won' => true],
                ['name' => 'Perdido', 'color' => '#ef4444', 'is_lost' => true],
            ];

            foreach ($defaults as $i => $stage) {
                $pipeline->stages()->create($stage + [
                    'tenant_id'  => $tenantId,
                    'sort_order' => $i,
                ]);
            }
        }

        return $pipeline;
    }
}
