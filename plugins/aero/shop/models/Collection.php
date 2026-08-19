<?php namespace Aero\Shop\Models;

use Model;
use Str;

class Collection extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    public $table = 'aero_shop_collections';

    public $fillable = ['tenant_id', 'parent_id', 'name', 'slug', 'description', 'is_active', 'sort_order'];

    protected $dates = ['deleted_at'];

    public $rules = [
        'tenant_id' => 'required|exists:aero_sites_tenants,id',
        'name'      => 'required|min:2|max:150',
        'slug'      => 'nullable|alpha_dash|max:150',
    ];

    public $belongsTo = [
        'tenant' => [\Aero\Sites\Models\Tenant::class],
        'parent' => [Collection::class, 'key' => 'parent_id'],
    ];

    public $hasMany = [
        'products' => [Product::class],
    ];

    public $attachOne = [
        'image' => \System\Models\File::class,
    ];

    public function beforeValidate()
    {
        if (!$this->slug && $this->name) {
            $this->slug = Str::slug($this->name);
        }
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function getParentIdOptions(): array
    {
        return self::where('tenant_id', $this->tenant_id)
            ->where('id', '<>', $this->id)
            ->pluck('name', 'id')
            ->all();
    }
}
