<?php namespace Aero\Sites\Models;

use Model;

class Page extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;
    use \October\Rain\Database\Traits\Sortable;

    public $table = 'aero_sites_pages';

    public $fillable = [
        'tenant_id', 'title', 'slug', 'content',
        'meta_title', 'meta_description', 'layout',
        'is_published', 'sort_order',
    ];

    protected $dates = ['deleted_at'];

    public $rules = [
        'tenant_id' => 'required|exists:aero_sites_tenants,id',
        'title'     => 'required|min:2|max:200',
        'slug'      => 'nullable|alpha_dash|max:200',
        'layout'    => 'required',
    ];

    public $belongsTo = [
        'tenant' => [Tenant::class],
    ];

    public $attachOne = [
        'og_image' => \System\Models\File::class,
    ];

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function getEffectiveMetaTitleAttribute(): string
    {
        return $this->meta_title ?: $this->title;
    }
}
