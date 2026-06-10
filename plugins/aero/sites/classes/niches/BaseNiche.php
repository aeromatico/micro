<?php namespace Aero\Sites\Classes\Niches;

use Aero\Sites\Models\ContactConfig;
use Aero\Sites\Models\NotificationChannel;
use Aero\Sites\Models\Page;
use Aero\Sites\Models\SeoConfig;
use Aero\Sites\Models\Tenant;
use Yaml;

abstract class BaseNiche implements NicheManagerInterface
{
    protected array $spec = [];

    public function __construct()
    {
        $this->spec = $this->loadSpec();
    }

    protected function loadSpec(): array
    {
        $base = $this->loadYaml('_base');
        $concrete = $this->loadYaml($this->getHandle());
        return array_replace_recursive($base, $concrete);
    }

    protected function loadYaml(string $handle): array
    {
        $path = plugins_path("aero/sites/specs/niches/{$handle}.yaml");
        if (!file_exists($path)) return [];
        return Yaml::parseFile($path);
    }

    public function getName(): string
    {
        return $this->spec['name'] ?? ucfirst($this->getHandle());
    }

    public function getIcon(): string
    {
        return $this->spec['icon'] ?? 'icon-globe';
    }

    public function getFeatures(): array
    {
        return $this->spec['features'] ?? [];
    }

    public function getDefaultPages(): array
    {
        return $this->spec['default_pages'] ?? [];
    }

    public function getSeoDefaults(): array
    {
        return $this->spec['seo_defaults'] ?? [];
    }

    public function getContactDefaults(): array
    {
        return $this->spec['contact_defaults'] ?? [];
    }

    public function getRecommendedNotification(): string
    {
        return $this->spec['recommended_notification'] ?? 'email';
    }

    public function provision(Tenant $tenant): void
    {
        $this->provisionSeoConfig($tenant);
        $this->provisionContactConfig($tenant);
        $this->provisionPages($tenant);
        $this->provisionDefaultChannel($tenant);
    }

    protected function provisionSeoConfig(Tenant $tenant): void
    {
        $defaults = $this->getSeoDefaults();
        SeoConfig::create([
            'tenant_id'           => $tenant->id,
            'title_format'        => $defaults['title_format'] ?? '%s | {name}',
            'default_description' => str_replace('{name}', $tenant->name, $defaults['default_description'] ?? ''),
            'sitemap_enabled'     => $defaults['sitemap_enabled'] ?? true,
            'robots_txt'          => $defaults['robots_txt'] ?? "User-agent: *\nAllow: /",
        ]);
    }

    protected function provisionContactConfig(Tenant $tenant): void
    {
        $defaults = $this->getContactDefaults();
        ContactConfig::create([
            'tenant_id'       => $tenant->id,
            'form_enabled'    => $defaults['form_enabled'] ?? true,
            'success_message' => $defaults['success_message'] ?? '¡Mensaje recibido!',
        ]);
    }

    protected function provisionPages(Tenant $tenant): void
    {
        foreach ($this->getDefaultPages() as $pageSpec) {
            Page::create([
                'tenant_id'    => $tenant->id,
                'title'        => $pageSpec['title'],
                'slug'         => $pageSpec['slug'],
                'layout'       => $pageSpec['layout'] ?? 'default',
                'is_published' => $pageSpec['is_published'] ?? true,
                'sort_order'   => $pageSpec['sort_order'] ?? 0,
                'content'      => '',
            ]);
        }
    }

    protected function provisionDefaultChannel(Tenant $tenant): void
    {
        $type = $this->getRecommendedNotification();
        NotificationChannel::create([
            'tenant_id'  => $tenant->id,
            'type'       => $type,
            'label'      => ucfirst($type) . ' principal',
            'config'     => [],
            'is_enabled' => false,
            'sort_order' => 1,
        ]);
    }
}
