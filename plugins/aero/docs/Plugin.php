<?php namespace Aero\Docs;

use Backend;
use BackendAuth;
use System\Classes\PluginBase;

/**
 * Documentación en Markdown con categorías multinivel (árbol NestedTree).
 * Cualquier categoría puede pasar a ser hija de otra desde el formulario
 * o arrastrándola en "Ordenar".
 */
class Plugin extends PluginBase
{
    public function pluginDetails(): array
    {
        return [
            'name'        => 'Docs',
            'description' => 'Documentación en Markdown con categorías multinivel.',
            'author'      => 'Aero',
            'icon'        => 'icon-book',
        ];
    }

    public function register(): void
    {
        $this->registerConsoleCommand('docs:import', \Aero\Docs\Console\ImportCommand::class);
        $this->registerConsoleCommand('docs:guides', \Aero\Docs\Console\GuidesPlanCommand::class);
        $this->registerConsoleCommand('docs:versions', \Aero\Docs\Console\VersionsCommand::class);
        $this->registerConsoleCommand('docs:backlog', \Aero\Docs\Console\BacklogCommand::class);
    }

    public function registerComponents(): array
    {
        return [
            \Aero\Docs\Components\Docs::class   => 'docs',
            \Aero\Docs\Components\Guides::class => 'guides',
        ];
    }

    /** Para los temas: {% if docs_enabled() %}. En la plataforma siempre; en un tenant, según el interruptor. */
    public function registerMarkupTags(): array
    {
        return [
            'functions' => [
                'docs_enabled' => fn () => \Aero\Docs\Classes\DocsScope::currentTenantId() === null
                    || \Aero\Docs\Models\TenantSetting::enabledFor(\Aero\Docs\Classes\DocsScope::currentTenantId()),
            ],
        ];
    }

    public function registerPermissions(): array
    {
        return [
            'aero.docs.manage' => ['tab' => 'Docs', 'label' => 'Administrar documentación: categorías y artículos'],
            'aero.docs.guides.review' => ['tab' => 'Docs', 'label' => 'Aprobar guías interactivas y cambios pendientes de la documentación'],
        ];
    }

    public function registerNavigation(): array
    {
        return [
            'docs' => [
                'label'       => 'Docs',
                'url'         => Backend::url('aero/docs/articles'),
                'icon'        => 'icon-book',
                'iconSvg'     => null,
                'permissions' => ['aero.docs.manage'],
                'order'       => 530,
                'sideMenu'    => [
                    'articles'   => ['label' => 'Artículos', 'icon' => 'icon-file-text-o', 'url' => Backend::url('aero/docs/articles'), 'permissions' => ['aero.docs.manage'], 'counter' => $this->pendingArticlesCount(), 'counterLabel' => 'Con cambios pendientes de revisión'],
                    'guides'     => ['label' => 'Guías interactivas', 'icon' => 'icon-desktop', 'url' => Backend::url('aero/docs/guides'), 'permissions' => ['aero.docs.guides.review'], 'counter' => $this->pendingGuidesCount(), 'counterLabel' => 'Pendientes de revisión'],
                    'categories' => ['label' => 'Categorías', 'icon' => 'icon-sitemap', 'url' => Backend::url('aero/docs/categories'), 'permissions' => ['aero.docs.manage']],
                    'reorder'    => ['label' => 'Ordenar árbol', 'icon' => 'icon-arrows-v', 'url' => Backend::url('aero/docs/categories/reorder'), 'permissions' => ['aero.docs.manage']],
                    'settings'   => ['label' => 'Configuración', 'icon' => 'icon-cog', 'url' => Backend::url('aero/docs/docssettings'), 'permissions' => ['aero.docs.manage']],
                ],
            ],
        ];
    }

    /** ¿El usuario actual es de los que revisan (superadmin)? Los tenants no ven contadores. */
    protected function canReview(): bool
    {
        return (bool) BackendAuth::getUser()?->hasAccess('aero.docs.guides.review');
    }

    /** Guías con propuesta de la IA por revisar. */
    protected function pendingGuidesCount(): int
    {
        try {
            return $this->canReview() ? \Aero\Docs\Models\Guide::pendingReview()->count() : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /** Artículos con cambios pendientes de revisión. */
    protected function pendingArticlesCount(): int
    {
        try {
            return $this->canReview() ? \Aero\Docs\Models\Article::whereNotNull('pending_content')->count() : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
