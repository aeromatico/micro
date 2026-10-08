<?php namespace Aero\Shop\Models;

use Model;
use Str;

/**
 * Grupo de extras de un plato (ej. "Tamaño", "Adicionales"). Las opciones
 * viven en el JSON `choices` ([{uid, name, price_delta, is_active}]) — así el
 * repeater del backend guarda todo de una vez, sin binding diferido.
 */
class ModifierGroup extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\Sortable;

    public $table = 'aero_shop_modifier_groups';

    public $fillable = ['tenant_id', 'product_id', 'name', 'min_select', 'max_select', 'choices', 'sort_order'];

    public $jsonable = ['choices'];

    public $rules = [
        'tenant_id'  => 'nullable|exists:aero_sites_tenants,id',
        'product_id' => 'nullable|exists:aero_shop_products,id',
        'name'       => 'required|max:100',
        'min_select' => 'nullable|integer|min:0',
        'max_select' => 'nullable|integer|min:1',
    ];

    public $belongsTo = [
        'product' => [Product::class],
    ];

    public function beforeSave()
    {
        if (!$this->tenant_id && $this->product_id) {
            $this->tenant_id = Product::find($this->product_id)?->tenant_id;
        }

        $this->min_select = (int) $this->min_select;
        $this->max_select = max(1, (int) $this->max_select);

        $choices = [];
        foreach ((array) $this->choices as $c) {
            if (trim((string) ($c['name'] ?? '')) === '') {
                continue;
            }
            $choices[] = [
                'uid'         => $c['uid'] ?? Str::random(8),
                'name'        => trim($c['name']),
                'price_delta' => max(0, (float) ($c['price_delta'] ?? 0)),
                'is_active'   => (bool) ($c['is_active'] ?? true),
            ];
        }
        $this->choices = $choices;
    }

    /** Opciones activas indexadas por uid. */
    public function activeChoices(): array
    {
        $out = [];
        foreach ((array) $this->choices as $c) {
            if (!empty($c['is_active'])) {
                $out[$c['uid']] = $c;
            }
        }
        return $out;
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
