<?php namespace Aero\Sites\Models;

use Model;

class SeoConfig extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_sites_seo_configs';

    public $fillable = [
        'tenant_id', 'title_format', 'default_description',
        'google_analytics_id', 'robots_txt', 'sitemap_enabled',
    ];

    public $rules = [
        'tenant_id'     => 'required|exists:aero_sites_tenants,id',
        'title_format'  => 'required',
    ];

    public $belongsTo = [
        'tenant' => [Tenant::class],
    ];

    public $attachOne = [
        'og_image' => \System\Models\File::class,
    ];

    public function buildTitle(string $pageTitle): string
    {
        $tenantName = $this->tenant->name ?? '';
        $format = $this->title_format ?: '%s | {name}';
        $format = str_replace('{name}', $tenantName, $format);
        return sprintf($format, $pageTitle);
    }
}
