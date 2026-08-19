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
    public string $cssVersion = '1';
    public string $jsVersion = '1';
    public bool $isShopEnabled = false;

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
        $this->cssVersion = $this->resolveAssetVersion('assets/css/app.min.css');
        $this->jsVersion  = $this->resolveAssetVersion('assets/js/app.js');

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

        // Dependencia blanda hacia Aero.Shop (plugin opcional) — si no está
        // instalado, isShopEnabled queda en false sin error.
        if (class_exists(\Aero\Shop\Models\ShopSettings::class)) {
            $this->isShopEnabled = (bool) \Aero\Shop\Models\ShopSettings::where('tenant_id', $this->tenant->id)->value('is_enabled');
        }
    }

    /**
     * Hash del mtime de un asset del theme, para invalidar el cache de
     * CDN/navegador (?v=...) en cada deploy — sin esto, Cloudflare puede
     * servir CSS/JS viejo hasta 12h (cache-control: max-age=43200) después
     * de recompilar.
     */
    protected function resolveAssetVersion(string $relativePath): string
    {
        // El theme del REQUEST actual (resuelto por sitio/dominio), no el
        // "active theme" global — este SaaS multi-tenant puede tener sitios
        // en un theme distinto al configurado como default en el sistema.
        $theme = $this->controller?->getTheme();
        if (!$theme) {
            return '1';
        }

        $path = $theme->getPath() . '/' . ltrim($relativePath, '/');
        if (!file_exists($path)) {
            return '1';
        }

        return (string) hash('crc32', (string) filemtime($path));
    }
}
