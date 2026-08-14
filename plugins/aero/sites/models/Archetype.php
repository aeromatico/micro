<?php namespace Aero\Sites\Models;

use Aero\Sites\Classes\Niches\NicheManager;
use Model;

/**
 * Secuencia de bloques Puck preconfigurada por nicho/área de negocio, para
 * que la generación con IA produzca resultados más homogéneos. El usuario
 * puede elegir uno explícitamente en el panel de generación, o dejar que
 * SiteGenerator elija uno al azar entre los afines al nicho del tenant.
 */
class Archetype extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\Sortable;

    public $table = 'aero_sites_archetypes';

    public $fillable = [
        'handle', 'name', 'niche_type', 'description',
        'blocks', 'recommended_tones', 'is_active', 'sort_order',
    ];

    protected $jsonable = ['blocks', 'recommended_tones'];

    public $rules = [
        'handle' => 'required|alpha_dash|unique:aero_sites_archetypes,handle',
        'name'   => 'required',
        'blocks' => 'required|array|min:1',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForNiche($query, ?string $nicheType)
    {
        return $query->where(function ($q) use ($nicheType) {
            $q->whereNull('niche_type');
            if ($nicheType) {
                $q->orWhere('niche_type', $nicheType);
            }
        });
    }

    public function getNicheTypeOptions(): array
    {
        return ['' => 'Universal (cualquier nicho)'] + app(NicheManager::class)->options();
    }

    /**
     * `blocks` se guarda como [{"block":"Hero"}, ...] (formato del campo
     * repeater del admin) — esto lo aplana a ['Hero', 'FeatureGrid', ...]
     * para quien lo consuma (SiteGenerator).
     */
    public function getBlocksListAttribute(): array
    {
        return array_values(array_filter(array_column($this->blocks ?? [], 'block')));
    }
}
