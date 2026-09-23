<?php namespace Aero\Services;

use Backend;
use System\Classes\PluginBase;

/**
 * Catálogo de servicios de tecnología (ecommerce y marketing). Genérico,
 * pero cada servicio puede ligarse a uno o varios plugins de la plataforma.
 */
class Plugin extends PluginBase
{
    public function pluginDetails(): array
    {
        return [
            'name'        => 'aero.services::lang.plugin.name',
            'description' => 'aero.services::lang.plugin.description',
            'author'      => 'Aero',
            'icon'        => 'icon-briefcase',
        ];
    }

    public function register(): void
    {
        $this->registerConsoleCommand('services.build-offer', \Aero\Services\Console\BuildOffer::class);
    }

    public function registerComponents(): array
    {
        return [\Aero\Services\Components\Services::class => 'services'];
    }

    public function registerPermissions(): array
    {
        return [
            'aero.services.manage' => [
                'tab'   => 'aero.services::lang.plugin.name',
                'label' => 'aero.services::lang.permissions.manage',
            ],
        ];
    }

    public function registerNavigation(): array
    {
        return [
            'services' => [
                'label'       => 'aero.services::lang.menu.services',
                'url'         => Backend::url('aero/services/services'),
                'icon'        => 'icon-briefcase',
                'permissions' => ['aero.services.manage'],
                'order'       => 565,
                'sideMenu'    => [
                    'services' => [
                        'label'       => 'aero.services::lang.menu.services',
                        'icon'        => 'icon-briefcase',
                        'url'         => Backend::url('aero/services/services'),
                        'permissions' => ['aero.services.manage'],
                    ],
                    'categories' => [
                        'label'       => 'aero.services::lang.menu.categories',
                        'icon'        => 'icon-folder-open-o',
                        'url'         => Backend::url('aero/services/categories'),
                        'permissions' => ['aero.services.manage'],
                    ],
                    'collections' => [
                        'label'       => 'aero.services::lang.menu.collections',
                        'icon'        => 'icon-th-large',
                        'url'         => Backend::url('aero/services/collections'),
                        'permissions' => ['aero.services.manage'],
                    ],
                ],
            ],
        ];
    }
}
