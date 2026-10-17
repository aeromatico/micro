<?php namespace Aero\Help;

use Backend;
use BackendAuth;
use Event;
use System\Classes\PluginBase;

/**
 * Ayuda guiada y contextual dentro del panel. Une lo que ya existe (docs,
 * guías interactivas, videos de los servicios) en un panel conversacional que
 * se abre desde el botón flotante de Ayuda. Primero rutas deterministas
 * (flujos paso a paso y búsqueda); la IA es una capa opcional posterior.
 * Escala a una persona reutilizando los tickets de Aero.Community / CRM.
 * Todas las integraciones con otros plugins son blandas (class_exists).
 */
class Plugin extends PluginBase
{
    public const ASSET_VERSION = '1.0.0';

    public function pluginDetails(): array
    {
        return [
            'name'        => 'Ayuda guiada',
            'description' => 'Panel de ayuda contextual: flujos paso a paso, búsqueda en la documentación, guías y videos, con escalado a tickets.',
            'author'      => 'Aero',
            'icon'        => 'icon-life-ring',
        ];
    }

    public function boot(): void
    {
        // El panel de ayuda aparece en todo el backend para quien tenga el permiso.
        Event::listen('backend.page.beforeDisplay', function ($controller) {
            $user = BackendAuth::getUser();
            if (!$user || request()->ajax()) {
                return;
            }
            if (!$user->hasAccess(['aero.help.use']) && !$user->hasAccess('aero.help.superadmin')) {
                return;
            }

            $controller->addCss('/plugins/aero/help/assets/css/help.css?cb=' . self::ASSET_VERSION);
            $controller->addJs('/plugins/aero/help/assets/js/help.js?cb=' . self::ASSET_VERSION, [
                'data-endpoint' => Backend::url('aero/help/panel'),
            ]);
        });
    }

    public function registerPermissions(): array
    {
        return [
            'aero.help.use' => [
                'tab'   => 'Ayuda guiada',
                'label' => 'Usar el panel de ayuda: flujos guiados, búsqueda y escalado a soporte',
            ],
            'aero.help.superadmin' => [
                'tab'   => 'Ayuda guiada',
                'label' => 'Administrar la ayuda: temas, flujos y vacíos de contenido',
            ],
        ];
    }

    public function registerNavigation(): array
    {
        return [
            'help' => [
                'label'       => 'Ayuda guiada',
                'url'         => Backend::url('aero/help/topics'),
                'icon'        => 'icon-life-ring',
                'iconSvg'     => null,
                'permissions' => ['aero.help.superadmin'],
                'order'       => 536,
                'sideMenu'    => [
                    'topics' => ['label' => 'Temas', 'icon' => 'icon-list', 'url' => Backend::url('aero/help/topics'), 'permissions' => ['aero.help.superadmin']],
                    'flows'  => ['label' => 'Flujos', 'icon' => 'icon-sitemap', 'url' => Backend::url('aero/help/flows'), 'permissions' => ['aero.help.superadmin']],
                    'gaps'   => ['label' => 'Búsquedas', 'icon' => 'icon-search', 'url' => Backend::url('aero/help/gaps'), 'permissions' => ['aero.help.superadmin']],
                ],
            ],
        ];
    }
}
