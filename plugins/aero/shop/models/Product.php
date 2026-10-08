<?php namespace Aero\Shop\Models;

use Model;
use Str;

class Product extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    public $table = 'aero_shop_products';

    public $fillable = [
        'tenant_id', 'collection_id', 'type', 'name', 'slug', 'description', 'sku',
        'has_variants', 'base_price', 'compare_at_price', 'cost_price', 'weight_grams',
        'requires_shipping', 'track_inventory', 'stock_quantity', 'allow_backorder',
        'status', 'is_featured', 'published_at', 'prep_minutes', 'min_quantity', 'barcode', 'is_internal', 'seo_title', 'seo_description',
    ];

    protected $dates = ['deleted_at', 'published_at'];

    public $rules = [
        'tenant_id'  => 'required|exists:aero_sites_tenants,id',
        'name'       => 'required|min:2|max:200',
        'slug'       => 'nullable|alpha_dash|max:200',
        'type'       => 'required|in:physical,digital',
        'status'     => 'required|in:draft,active,archived',
        'base_price' => 'required|numeric|min:0',
    ];

    public $belongsTo = [
        'tenant'     => [\Aero\Sites\Models\Tenant::class],
        'collection' => [Collection::class],
    ];

    public $hasMany = [
        'options'  => [ProductOption::class],
        'variants' => [ProductVariant::class],
        'modifier_groups' => [ModifierGroup::class, 'order' => 'sort_order'],
    ];

    public $belongsToMany = [
        'collections' => [
            Collection::class,
            'table' => 'aero_shop_product_collection',
        ],
    ];

    public $attachMany = [
        'images' => \System\Models\File::class,
    ];

    public $attachOne = [
        'digital_file' => \System\Models\File::class,
    ];

    public function beforeValidate()
    {
        // Los campos numéricos vacíos del formulario llegan como null: las columnas
        // obligatorias toman su valor por defecto en vez de fallar en la base de datos.
        foreach (['stock_quantity' => 0, 'min_quantity' => 1, 'base_price' => 0] as $field => $default) {
            if ($this->{$field} === null || $this->{$field} === '') {
                $this->{$field} = $default;
            }
        }
        foreach (['compare_at_price', 'cost_price', 'weight_grams', 'prep_minutes', 'sku'] as $field) {
            if ($this->{$field} === '') {
                $this->{$field} = null;
            }
        }

        if (!$this->slug && $this->name) {
            $this->slug = Str::slug($this->name);
        }
        // Dos platos con el mismo nombre no deben chocar: el identificador se hace único por tienda.
        if ($this->slug && $this->tenant_id && ($this->isDirty('slug') || !$this->exists)) {
            $base = $this->slug;
            $n = 2;
            while (static::withTrashed()->where('tenant_id', $this->tenant_id)->where('slug', $this->slug)
                ->when($this->exists, fn ($q) => $q->where('id', '!=', $this->id))->exists()) {
                $this->slug = $base . '-' . $n++;
            }
        }
        // Restaurante: todo es físico (platos); sin tipo definido, también.
        if (!$this->type || ($this->tenant_id && ShopSettings::isRestaurantForTenant((int) $this->tenant_id))) {
            $this->type = 'physical';
        }
        if ($this->type === 'digital') {
            $this->requires_shipping = false;
        }
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /** Publicados y visibles: excluye los productos internos (ej. «Venta libre» del POS). */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')->where('is_internal', false);
    }

    public function getCollectionIdOptions(): array
    {
        return Collection::where('tenant_id', $this->tenant_id)->pluck('name', 'id')->all();
    }

    /**
     * Precio a mostrar en catálogo/tarjeta: con variantes, el más bajo entre
     * las activas (patrón "Desde $X"); sin variantes, el precio base.
     */
    public function getDisplayPriceAttribute(): float
    {
        if (!$this->has_variants) {
            return (float) $this->base_price;
        }

        $min = $this->variants()->where('is_active', true)->min('price');
        return (float) ($min ?? $this->base_price);
    }

    public function getHasPriceRangeAttribute(): bool
    {
        if (!$this->has_variants) {
            return false;
        }

        $prices = $this->variants()->where('is_active', true)->pluck('price');
        return $prices->unique()->count() > 1;
    }

    /**
     * Stock a mostrar: con variantes, la suma de todas las activas; sin
     * variantes, el stock propio del producto.
     */
    public function getDisplayStockAttribute(): int
    {
        if (!$this->has_variants) {
            return (int) $this->stock_quantity;
        }

        return (int) $this->variants()->where('is_active', true)->sum('stock_quantity');
    }

    public function getIsInStockAttribute(): bool
    {
        if (!ShopSettings::inventoryEnabledForTenant($this->tenant_id)) {
            return true;
        }

        if (!$this->track_inventory || $this->allow_backorder) {
            return true;
        }

        return $this->display_stock > 0;
    }
}
