<?php namespace Aero\Sites\Components;

use Aero\Sites\Models\Tenant;
use Cms\Classes\ComponentBase;

class TenantSeo extends ComponentBase
{
    public ?Tenant $tenant = null;
    public string $pageTitle = '';
    public string $metaTitle = '';
    public string $metaDescription = '';
    public ?string $ogImageUrl = null;
    public ?string $analyticsId = null;
    public string $canonicalUrl = '';
    public string $primaryColor = '#6366f1';

    public function componentDetails(): array
    {
        return [
            'name'        => 'Sites SEO',
            'description' => 'Inyecta meta tags SEO del tenant activo.',
        ];
    }

    public function defineProperties(): array
    {
        return [
            'pageTitle' => [
                'title'       => 'Título de página',
                'description' => 'Título específico de esta página (opcional)',
                'type'        => 'string',
                'default'     => '',
            ],
            'metaDescription' => [
                'title'       => 'Descripción',
                'description' => 'Sobreescribe la descripción por defecto',
                'type'        => 'string',
                'default'     => '',
            ],
        ];
    }

    public function onRun(): void
    {
        $host = request()->getHost();
        $this->tenant = Tenant::resolveFromDomain($host);

        if (!$this->tenant) return;

        $this->tenant->load(['seoConfig', 'logo', 'favicon']);
        $seo = $this->tenant->seoConfig;

        $rawTitle = $this->property('pageTitle') ?: $this->tenant->name;
        $this->pageTitle = $seo ? $seo->buildTitle($rawTitle) : $rawTitle;

        $this->metaDescription = $this->property('metaDescription')
            ?: ($seo?->default_description ?? '');

        $this->ogImageUrl = $seo?->og_image?->getPath();
        $this->analyticsId = $seo?->google_analytics_id;
        $this->canonicalUrl = url()->current();
        $this->primaryColor = $this->tenant->primary_color ?? '#6366f1';
    }
}
