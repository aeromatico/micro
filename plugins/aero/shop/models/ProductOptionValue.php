<?php namespace Aero\Shop\Models;

use Model;

class ProductOptionValue extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\Sortable;

    public $table = 'aero_shop_product_option_values';

    public $fillable = ['tenant_id', 'product_option_id', 'value', 'sort_order'];

    // El Repeater en modo relación (campo "values" en el form de Opción)
    // guarda cada fila con sus datos reales ANTES de que el binding diferido
    // hacia el padre (product_option_id) se confirme — eso solo ocurre
    // cuando la propia Opción termina de guardarse. En ese punto intermedio
    // product_option_id (y por lo tanto tenant_id, derivado de él) todavía
    // es null, así que ninguno de los tres puede ser 'required' a nivel de
    // modelo. Ambas FKs y el valor quedan bien puestos en el guardado final
    // (ver beforeValidate) — 'value' además es required en la UI (fields.yaml
    // del repeater), lo que basta en la práctica.
    public $rules = [
        'tenant_id'         => 'nullable|exists:aero_sites_tenants,id',
        'product_option_id' => 'nullable|exists:aero_shop_product_options,id',
        'value'             => 'nullable|max:100',
    ];

    public $belongsTo = [
        'option' => [ProductOption::class, 'key' => 'product_option_id'],
    ];

    public $belongsToMany = [
        'variants' => [
            ProductVariant::class,
            'table'   => 'aero_shop_variant_opt_values',
            'key'     => 'product_option_value_id',
            'otherKey' => 'variant_id',
        ],
    ];

    public function beforeValidate()
    {
        if (!$this->tenant_id && $this->product_option_id) {
            $this->tenant_id = ProductOption::find($this->product_option_id)?->tenant_id;
        }
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Usado como "scope" del campo option_values (productvariant/fields.yaml)
     * para que el checkbox list solo muestre los valores de las opciones del
     * producto al que pertenece la variante — no todos los valores de la BD.
     * ProductVariant::make() ya deja product_id poblado en memoria aunque la
     * variante sea nueva y aún no se haya guardado.
     *
     * Ordena por options.sort_order/id y luego por values.sort_order/id, para
     * que el checkbox list agrupe por opción (Talla, Color...) en el orden en
     * que se crearon, en vez de mezclar todos los valores por su propio id.
     */
    public function scopeForVariantProduct($query, ProductVariant $variant)
    {
        return $query
            ->join('aero_shop_product_options', 'aero_shop_product_options.id', '=', 'aero_shop_product_option_values.product_option_id')
            ->where('aero_shop_product_options.product_id', $variant->product_id)
            ->orderBy('aero_shop_product_options.sort_order')
            ->orderBy('aero_shop_product_options.id')
            ->orderBy('aero_shop_product_option_values.sort_order')
            ->orderBy('aero_shop_product_option_values.id')
            ->select('aero_shop_product_option_values.*');
    }
}
