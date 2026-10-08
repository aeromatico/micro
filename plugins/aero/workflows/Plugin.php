<?php namespace Aero\Workflows;

use Backend;
use Event;
use System\Classes\PluginBase;

/**
 * Motor de automatización por grafos de nodos. Independiente: no depende de
 * Chatbots. Hello, Connector, Notify y Credits son integraciones blandas.
 * Con Aero.Chatbots instalado, un workflow puede ofrecerse como herramienta
 * del Super Chatbot IA — opcional por bot (categoría «workflows») y por
 * workflow (`expose_as_tool`).
 */
class Plugin extends PluginBase
{
    public function pluginDetails(): array
    {
        return [
            'name'        => 'Workflows',
            'description' => 'Automatizaciones con nodos: disparadores, condiciones, HTTP, mensajes y notificaciones. Opcional para el Super Chatbot IA.',
            'author'      => 'Aero',
            'icon'        => 'icon-sitemap',
        ];
    }

    public function register(): void
    {
        $this->registerConsoleCommand('workflows.skill-catalog', \Aero\Workflows\Console\GenerateSkillCatalog::class);
    }

    public function boot(): void
    {
        $this->app['router']->group([], function () {
            require __DIR__ . '/routes.php';
        });

        // Disparadores por evento de la plataforma / mensaje entrante.
        Event::listen('aero.*', function (string $eventName, array $payload) {
            \Aero\Workflows\Classes\Triggers::handle($eventName, $payload);
        });

        // Integración opcional con el Super Chatbot IA.
        Event::listen('aero.chatbots.registerAiTools', function (?int $tenantId = null) {
            // Las del cliente (workflows ofrecidos como herramienta) + las del constructor de workflows.
            return \Aero\Workflows\Classes\AiTools::tools($tenantId) + \Aero\Workflows\Classes\BuilderTools::tools();
        });

        Event::listen('aero.chatbots.registerAiToolCategories', function () {
            return [
                \Aero\Workflows\Classes\AiTools::CATEGORY => 'Automatizaciones: ejecutar workflows existentes',
                \Aero\Workflows\Classes\BuilderTools::CATEGORY => 'Automatizaciones: diseñar workflows nuevos (quedan en borrador)',
            ];
        });
    }

    public function registerFormWidgets(): array
    {
        return [
            \Aero\Workflows\FormWidgets\WorkflowEditor::class => [
                'label' => 'Editor visual de workflows',
                'code'  => 'workflowEditor',
            ],
        ];
    }

    public function registerPermissions(): array
    {
        return [
            'aero.workflows.use' => [
                'tab'   => 'Workflows',
                'label' => 'Crear y administrar sus workflows y ver sus ejecuciones',
            ],
            'aero.workflows.superadmin' => [
                'tab'   => 'Workflows',
                'label' => 'Administrar workflows de todos los tenants',
            ],
        ];
    }

    public function registerNavigation(): array
    {
        $perms = ['aero.workflows.use', 'aero.workflows.superadmin'];

        return [
            'workflows' => [
                'label'       => 'Workflows',
                'url'         => Backend::url('aero/workflows/workflows'),
                'icon'        => 'icon-sitemap',
                'iconSvg'     => null,
                'permissions' => $perms,
                'order'       => 535,
                'sideMenu'    => [
                    'workflows' => ['label' => 'Workflows', 'icon' => 'icon-sitemap', 'url' => Backend::url('aero/workflows/workflows'), 'permissions' => $perms],
                    'runs'      => ['label' => 'Ejecuciones', 'icon' => 'icon-list', 'url' => Backend::url('aero/workflows/runs'), 'permissions' => $perms],
                ],
            ],
        ];
    }
}
