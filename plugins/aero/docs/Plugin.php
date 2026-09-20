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

    public function registerComponents(): array
    {
        return [\Aero\Docs\Components\Docs::class => 'docs'];
    }

    public function registerPermissions(): array
    {
        return [
            'aero.docs.manage' => ['tab' => 'Docs', 'label' => 'Administrar documentación: categorías y artículos'],
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
                    'articles'   => ['label' => 'Artículos', 'icon' => 'icon-file-text-o', 'url' => Backend::url('aero/docs/articles'), 'permissions' => ['aero.docs.manage']],
                    'categories' => ['label' => 'Categorías', 'icon' => 'icon-sitemap', 'url' => Backend::url('aero/docs/categories'), 'permissions' => ['aero.docs.manage']],
                    'reorder'    => ['label' => 'Ordenar árbol', 'icon' => 'icon-arrows', 'url' => Backend::url('aero/docs/categories/reorder'), 'permissions' => ['aero.docs.manage']],
                ],
            ],
        ];
    }
}
