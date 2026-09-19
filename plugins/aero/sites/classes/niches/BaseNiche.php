<?php namespace Aero\Sites\Classes\Niches;

use Aero\Sites\Models\ContactConfig;
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

    public function getBasePrompt(): string
    {
        return $this->spec['base_prompt'] ?? '';
    }

    public function getToneInstructions(): string
    {
        return $this->spec['tone_instructions'] ?? '';
    }

    public function getTargetAudience(): string
    {
        return $this->spec['target_audience'] ?? '';
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

    /**
     * Solo se provisionan la home y contacto: son las dos únicas rutas fijas
     * del theme (contacto.htm apunta a sitesPageDetail slug="contacto" y
     * hace 404 si no existe el Page). Las demás entradas de default_pages
     * (servicios, portafolio, nosotros, etc.) son solo texto de relleno del
     * nicho — antes se creaban igual, dejando páginas vacías no pedidas por
     * nadie; ahora el admin las agrega él mismo, o las genera la IA.
     */
    protected function provisionPages(Tenant $tenant): void
    {
        foreach ($this->getDefaultPages() as $pageSpec) {
            if (!in_array($pageSpec['slug'], ['', 'contacto'], true)) {
                continue;
            }

            $rawContent = $pageSpec['content'] ?? '';
            Page::create([
                'tenant_id'      => $tenant->id,
                'title'          => str_replace('{name}', $tenant->name, $pageSpec['title']),
                'slug'           => $pageSpec['slug'],
                'layout'         => $pageSpec['layout'] ?? 'default',
                'is_published'   => $pageSpec['is_published'] ?? true,
                'sort_order'     => $pageSpec['sort_order'] ?? 0,
                'content'        => str_replace('{name}', $tenant->name, $rawContent),
                // La home queda marcada como placeholder hasta que el admin
                // genere su primer landing con IA o guarde contenido propio —
                // ver home.htm. Las demás páginas (contacto, etc.) no aplican:
                // ni GenerateAiSiteJob ni ContentEditor las tocan.
                'is_placeholder' => $pageSpec['slug'] === '',
            ]);
        }
    }

    protected function provisionDefaultChannel(Tenant $tenant): void
    {
        if (!class_exists(\Aero\Notify\Models\Channel::class)) {
            return;
        }

        $type = $this->getRecommendedNotification();
        \Aero\Notify\Models\Channel::create([
            'tenant_id'  => $tenant->id,
            'channel'    => $type,
            'label'      => ucfirst($type) . ' principal',
            'config'     => [],
            'is_enabled' => false,
            'sort_order' => 1,
        ]);
    }
}
