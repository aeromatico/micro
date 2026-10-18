<?php namespace Aero\Shortcuts;

use Backend;
use BackendAuth;
use Event;
use System\Classes\PluginBase;

/**
 * Atajos del dashboard: el usuario del tenant arma su propia fila de botones
 * (formularios, listas o URLs personalizadas) y la arrastra para ordenarla.
 * Independiente: solo necesita el Dashboard del core.
 */
class Plugin extends PluginBase
{
    const ASSET_VERSION = '4';

    public function pluginDetails(): array
    {
        return [
            'name'        => 'Atajos',
            'description' => 'Botones rápidos personalizables y arrastrables para el dashboard.',
            'author'      => 'Aero',
            'icon'        => 'icon-th-large',
        ];
    }

    public function boot(): void
    {
        // El widget se inyecta por AJAX en el dashboard: el JS/CSS debe estar en la página.
        Event::listen('backend.page.beforeDisplay', function ($controller) {
            $user = BackendAuth::getUser();
            if (!$user || request()->ajax()) {
                return;
            }
            if (!$user->hasAccess('aero.shortcuts.use') && !$user->hasAccess('aero.shortcuts.superadmin')) {
                return;
            }

            $controller->addCss('/plugins/aero/shortcuts/assets/css/shortcuts.css?v=' . self::ASSET_VERSION);
            $controller->addJs('/plugins/aero/shortcuts/assets/js/shortcuts.js?v=' . self::ASSET_VERSION);
        });
    }

    public function registerPermissions(): array
    {
        return [
            'aero.shortcuts.use' => [
                'tab'   => 'Atajos',
                'label' => 'Usar atajos: crear y ordenar sus botones rápidos del dashboard',
            ],
            'aero.shortcuts.superadmin' => [
                'tab'   => 'Atajos',
                'label' => 'Administrar atajos de todos los tenants y los de la plataforma',
            ],
        ];
    }

    public function registerNavigation(): array
    {
        return [
            'shortcuts' => [
                'label'       => 'Atajos',
                'url'         => Backend::url('aero/shortcuts/items'),
                'icon'        => 'icon-th-large',
                'iconSvg'     => null,
                'permissions' => ['aero.shortcuts.use', 'aero.shortcuts.superadmin'],
                'order'       => 930,
                'sideMenu'    => [
                    'items' => ['label' => 'Mis atajos', 'icon' => 'icon-th-large', 'url' => Backend::url('aero/shortcuts/items'), 'permissions' => ['aero.shortcuts.use', 'aero.shortcuts.superadmin']],
                ],
            ],
        ];
    }

    public function registerReportWidgets(): array
    {
        return [
            \Aero\Shortcuts\ReportWidgets\Shortcuts::class => [
                'label'       => 'Atajos (botones rápidos)',
                'context'     => 'dashboard',
                'permissions' => ['aero.shortcuts.use', 'aero.shortcuts.superadmin'],
            ],
        ];
    }
}
