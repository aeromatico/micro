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
        'status', 'is_featured', 'published_at', 'seo_title', 'seo_description',
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
        if (!$this->slug && $this->name) {
            $this->slug = Str::slug($this->name);
        }
        if ($this->type === 'digital') {
            $this->requires_shipping = false;
        }
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
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
