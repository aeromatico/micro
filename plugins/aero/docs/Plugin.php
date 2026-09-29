<?php namespace Aero\Docs;

use Backend;
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
                'counter'     => $this->backlogCount(),
                'counterLabel' => 'Pendientes de generar (CLI docs-cli)',
                'sideMenu'    => [
                    'articles'   => ['label' => 'Artículos', 'icon' => 'icon-file-text-o', 'url' => Backend::url('aero/docs/articles'), 'permissions' => ['aero.docs.manage']],
                    'guides'     => ['label' => 'Guías interactivas', 'icon' => 'icon-desktop', 'url' => Backend::url('aero/docs/guides'), 'permissions' => ['aero.docs.manage'], 'counter' => $this->pendingReviewCount(), 'counterLabel' => 'Pendientes de revisión'],
                    'categories' => ['label' => 'Categorías', 'icon' => 'icon-sitemap', 'url' => Backend::url('aero/docs/categories'), 'permissions' => ['aero.docs.manage']],
                    'reorder'    => ['label' => 'Ordenar árbol', 'icon' => 'icon-arrows-v', 'url' => Backend::url('aero/docs/categories/reorder'), 'permissions' => ['aero.docs.manage']],
                ],
            ],
        ];
    }

    /** Guías con propuesta de la IA más artículos con cambios pendientes; alimenta el contador del menú. */
    protected function pendingReviewCount(): int
    {
        try {
            return \Aero\Docs\Models\Guide::pendingReview()->count()
                + \Aero\Docs\Models\Article::whereNotNull('pending_content')->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /** Elementos por generar según el último escaneo del cron (Cache); 0 si aún no hay. */
    protected function backlogCount(): int
    {
        try {
            return (int) (\Cache::get('aero.docs.backlog')['total'] ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
