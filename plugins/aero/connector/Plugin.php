<?php namespace Aero\Connector;

use Backend;
use Event;
use Route;
use System\Classes\PluginBase;

/**
 * Conector genérico de endpoints HTTP, modelos de IA y APIs/webhooks
 * sociales, reutilizable entre proyectos: define un "tipo" una vez
 * (Aero\Connector\Classes\TypeRegistry) y cualquier integración nueva
 * solo necesita crear un Connector desde el backend, sin tocar código.
 */
class Plugin extends PluginBase
{
    public function pluginDetails(): array
    {
        return [
            'name'        => 'aero.connector::lang.plugin.name',
            'description' => 'aero.connector::lang.plugin.description',
            'author'      => 'Aero',
            'icon'        => 'icon-plug',
            'homepage'    => 'https://github.com/aeromatico/connector',
        ];
    }

    public function boot(): void
    {
        $this->registerWebhookRoute();
        $this->registerBuiltInTypes();
    }

    protected function registerWebhookRoute(): void
    {
        // Público por necesidad (lo llaman terceros): el throttle y la
        // verificación de firma por endpoint son la única defensa.
        Route::post('connector/webhooks/{slug}', [
            \Aero\Connector\Http\Controllers\Api\WebhookReceiverController::class, 'handle',
        ])->middleware('throttle:300,1');
    }

    /**
     * Tipos de fábrica, disponibles sin depender de ningún otro plugin.
     * Un plugin consumidor agrega los suyos escuchando el mismo evento.
     */
    protected function registerBuiltInTypes(): void
    {
        Event::listen('aero.connector.registerTypes', function () {
            return [
                'http' => [
                    'label'    => trans('aero.connector::lang.types.http'),
                    'category' => 'http',
                    'driver'   => \Aero\Connector\Drivers\HttpDriver::class,
                    'auth'     => 'bearer',
                ],
                'ai_openai_compatible' => [
                    'label'    => trans('aero.connector::lang.types.ai_openai_compatible'),
                    'category' => 'ai',
                    'driver'   => \Aero\Connector\Drivers\AiOpenAiCompatibleDriver::class,
                    'auth'     => 'none',
                ],
                'ai_anthropic' => [
                    'label'    => trans('aero.connector::lang.types.ai_anthropic'),
                    'category' => 'ai',
                    'driver'   => \Aero\Connector\Drivers\AiAnthropicDriver::class,
                    'auth'     => 'none',
                ],
            ];
        });
    }

    public function registerPermissions(): array
    {
        return [
            'aero.connector.manage_connectors' => [
                'tab'   => 'aero.connector::lang.plugin.name',
                'label' => 'aero.connector::lang.permissions.manage_connectors',
            ],
            'aero.connector.manage_webhooks' => [
                'tab'   => 'aero.connector::lang.plugin.name',
                'label' => 'aero.connector::lang.permissions.manage_webhooks',
            ],
            'aero.connector.view_logs' => [
                'tab'   => 'aero.connector::lang.plugin.name',
                'label' => 'aero.connector::lang.permissions.view_logs',
            ],
        ];
    }

    public function registerNavigation(): array
    {
        return [
            'connector' => [
                'label'       => 'aero.connector::lang.menu.connectors',
                'url'         => Backend::url('aero/connector/connectors'),
                'icon'        => 'icon-plug',
                'permissions' => [
                    'aero.connector.manage_connectors', 'aero.connector.manage_webhooks', 'aero.connector.view_logs',
                ],
                'order'       => 560,
                'sideMenu'    => [
                    'connectors' => [
                        'label'       => 'aero.connector::lang.menu.connectors',
                        'icon'        => 'icon-plug',
                        'url'         => Backend::url('aero/connector/connectors'),
                        'permissions' => ['aero.connector.manage_connectors'],
                    ],
                    'webhookendpoints' => [
                        'label'       => 'aero.connector::lang.menu.webhooks',
                        'icon'        => 'icon-satellite-dish',
                        'url'         => Backend::url('aero/connector/webhookendpoints'),
                        'permissions' => ['aero.connector.manage_webhooks'],
                    ],
                    'connectorlogs' => [
                        'label'       => 'aero.connector::lang.menu.logs',
                        'icon'        => 'icon-list-alt',
                        'url'         => Backend::url('aero/connector/connectorlogs'),
                        'permissions' => ['aero.connector.view_logs'],
                    ],
                ],
            ],
        ];
    }
}
