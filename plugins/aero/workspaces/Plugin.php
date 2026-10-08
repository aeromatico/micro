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

    public function boot(): void
    {
        // Herramientas del Mercado y la Oficina para el Super Chatbot IA y el MCP (acoplamiento blando: solo escucha el evento).
        \Event::listen('aero.chatbots.registerAiTools', fn (?int $tenantId = null) => \Aero\Workspaces\Classes\Tools::tools());

        \Event::listen('aero.chatbots.registerAiTools', fn (?int $tenantId = null) => \Aero\Workspaces\Classes\ApiTools::tools());

        \Event::listen('aero.chatbots.registerAiToolCategories', fn () => [
            \Aero\Workspaces\Classes\Tools::CATEGORY => 'Workspaces: mercado y oficina de agentes',
            \Aero\Workspaces\Classes\ApiTools::CATEGORY => 'Catálogo de la API de la plataforma',
        ]);
    }

    public function registerNavigation(): array
    {
        $use = ['aero.workspaces.use', 'aero.workspaces.superadmin'];

        return [
            'workspaces' => [
                'label'       => 'Workspaces',
                'url'         => \Backend::url('aero/workspaces/market'),
                'icon'        => 'icon-users',
                'permissions' => $use,
                'order'       => 570,
                'sideMenu'    => [
                    'market' => [
                        'label'       => 'Mercado',
                        'icon'        => 'icon-shopping-cart',
                        'url'         => \Backend::url('aero/workspaces/market'),
                        'permissions' => $use,
                    ],
                    'office' => [
                        'label'       => 'Oficina',
                        'icon'        => 'icon-building',
                        'url'         => \Backend::url('aero/workspaces/office'),
                        'permissions' => $use,
                    ],
                    'charges' => [
                        'label'       => 'Cobros',
                        'icon'        => 'icon-money',
                        'url'         => \Backend::url('aero/workspaces/charges'),
                        'permissions' => ['aero.workspaces.superadmin'],
                    ],
                    'staff' => [
                        'label'       => 'Staff (catálogo)',
                        'icon'        => 'icon-user',
                        'url'         => \Backend::url('aero/workspaces/staff'),
                        'permissions' => ['aero.workspaces.superadmin'],
                    ],
                ],
            ],
        ];
    }

    public function registerSettings(): array
    {
        return [
            'settings' => [
                'label'       => 'Workspaces',
                'description' => 'Cobro de puntos al contratar agentes y enviar encargos.',
                'category'    => 'Sistema',
                'icon'        => 'icon-users',
                'class'       => \Aero\Workspaces\Models\Settings::class,
                'order'       => 515,
                'permissions' => ['aero.workspaces.superadmin'],
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
            'aero.workspaces.use' => [
                'tab'   => 'Workspaces',
                'label' => 'Usar el Mercado y la Oficina de agentes (contratar y enviar encargos)',
            ],
        ];
    }
}
