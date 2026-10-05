<?php namespace Aero\Workspaces;

use System\Classes\PluginBase;

/**
 * Espacios de trabajo: el mercado de staff (agentes IA hoy, personas después),
 * sus tarifas, sus skills y las contrataciones de cada tenant.
 */
class Plugin extends PluginBase
{
    public function pluginDetails(): array
    {
        return [
            'name'        => 'Workspaces',
            'description' => 'Mercado de staff: agentes IA con prompt, skills, tarifas por contratación y por tarea.',
            'author'      => 'Aero',
            'icon'        => 'icon-users',
        ];
    }

    public function registerNavigation(): array
    {
        return [
            'workspaces' => [
                'label'       => 'Workspaces',
                'url'         => \Backend::url('aero/workspaces/staff'),
                'icon'        => 'icon-users',
                'permissions' => ['aero.workspaces.superadmin'],
                'order'       => 570,
                'sideMenu'    => [
                    'staff' => [
                        'label'       => 'Staff',
                        'icon'        => 'icon-user',
                        'url'         => \Backend::url('aero/workspaces/staff'),
                        'permissions' => ['aero.workspaces.superadmin'],
                    ],
                ],
            ],
        ];
    }

    public function registerPermissions(): array
    {
        return [
            'aero.workspaces.superadmin' => [
                'tab'   => 'Workspaces',
                'label' => 'Administrar el catálogo de staff, prompts y tarifas',
            ],
        ];
    }
}
