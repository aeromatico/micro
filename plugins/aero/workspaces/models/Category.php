<?php namespace Aero\Workspaces\Models;

use Model;

/**
 * Categoría del catálogo de agentes (filtro del Mercado). Dinámica: la
 * administra el superadmin en Staff → Categorías.
 */
class Category extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_workspaces_categories';

    public $fillable = ['code', 'label', 'sort_order', 'is_active'];

    public $rules = [
        'code'  => 'required|alpha_dash|max:40|unique:aero_workspaces_categories,code',
        'label' => 'required|max:80',
    ];

    public $attributes = ['is_active' => true, 'sort_order' => 0];

    public function beforeDelete(): void
    {
        if (Staff::where('category', $this->code)->exists()) {
            throw new \ApplicationException('Hay agentes en esta categoría: muévelos a otra antes de eliminarla, o desactívala.');
        }
    }

    public function getAgentsAttribute(): int
    {
        return Staff::where('category', $this->code)->count();
    }

    /** Categorías activas en orden: [código => nombre]. */
    public static function options(): array
    {
        try {
            return static::where('is_active', true)->orderBy('sort_order')->orderBy('label')->pluck('label', 'code')->all();
        }
        catch (\Throwable) {
            return []; // antes de migrar no hay tabla
        }
    }
}
