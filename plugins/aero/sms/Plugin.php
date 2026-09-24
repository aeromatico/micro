<?php namespace Aero\Sms;

use Backend;
use Event;
use System\Classes\PluginBase;

/**
 * Plataforma de envío de SMS. Independiente: las credenciales del proveedor
 * son globales y se consume a través de la API REST propia, autenticada con
 * las keys de aero/api. Cada mensaje queda atribuido al tenant dueño de la
 * key, y si Aero.Credits está instalado se le descuentan créditos.
 */
class Plugin extends PluginBase
{
    /** El middleware `aero.api` y la atribución por key viven en aero/api. */
    public $require = ['Aero.Api'];

    public function pluginDetails(): array
    {
        return [
            'name'        => 'SMS',
            'description' => 'Envío de SMS simples y masivos con API propia, atribución por tenant y cobro en créditos.',
            'author'      => 'Aero',
            'icon'        => 'icon-commenting',
        ];
    }

    public function boot(): void
    {
        $this->app['router']->group([], function () {
            require __DIR__ . '/routes.php';
        });

        Event::listen('aero.api.registerScopes', function () {
            return ['sms' => [
                'label'  => 'SMS',
                'scopes' => \Aero\Sms\Classes\Api\Scopes::all(),
            ]];
        });

        if (!class_exists(\Aero\Api\Classes\EndpointRegistry::class)) {
            return;
        }

        Event::listen('aero.api.registerEndpoints', function () {
            $scopes = \Aero\Sms\Classes\Api\Scopes::class;

            return ['sms' => [
                'label' => 'SMS',
                'endpoints' => [
                    [
                        'method' => 'POST', 'path' => '/api/v1/sms/messages', 'scope' => $scopes::SEND,
                        'summary' => 'Envía un SMS simple.',
                        'body_example' => "{\n    \"to\": \"+59170000000\",\n    \"body\": \"Hola, este es un SMS de prueba.\"\n}",
                        'credit_action' => \Aero\Sms\Classes\Billing::ACTION,
                        'credit_note'   => 'por segmento — un SMS puede usar varios',
                    ],
                    [
                        'method' => 'POST', 'path' => '/api/v1/sms/messages/{uuid}/cancel', 'scope' => $scopes::SEND,
                        'summary' => 'Cancela un mensaje en cola.',
                        'path_params' => [['name' => 'uuid', 'type' => 'string', 'required' => true, 'help' => 'UUID del mensaje.']],
                    ],
                    [
                        'method' => 'POST', 'path' => '/api/v1/sms/batches', 'scope' => $scopes::SEND,
                        'summary' => 'Envía un lote de SMS a varios destinatarios.',
                        'body_example' => "{\n    \"name\": \"Campaña de prueba\",\n    \"body\": \"Hola {{nombre}}\",\n    \"recipients\": [\n        {\"to\": \"+59170000000\", \"vars\": {\"nombre\": \"Ana\"}}\n    ]\n}",
                        'credit_action' => \Aero\Sms\Classes\Billing::ACTION,
                        'credit_note'   => 'por segmento, por destinatario — un SMS puede usar varios segmentos',
                    ],
                    [
                        'method' => 'POST', 'path' => '/api/v1/sms/batches/{uuid}/cancel', 'scope' => $scopes::SEND,
                        'summary' => 'Cancela un lote (mensajes aún no enviados).',
                        'path_params' => [['name' => 'uuid', 'type' => 'string', 'required' => true, 'help' => 'UUID del lote.']],
                    ],
                    [
                        'method' => 'POST', 'path' => '/api/v1/sms/quote', 'scope' => $scopes::SEND,
                        'summary' => 'Cotiza segmentos y créditos sin enviar nada.',
                        'body_example' => "{\n    \"body\": \"Hola, este es un SMS de prueba.\",\n    \"recipients\": 1\n}",
                    ],
                    [
                        'method' => 'GET', 'path' => '/api/v1/sms/messages', 'scope' => $scopes::READ,
                        'summary' => 'Lista de mensajes.',
                        'query' => [
                            ['name' => 'status', 'type' => 'string', 'help' => 'queued, sent, delivered, failed…'],
                            ['name' => 'reference', 'type' => 'string'],
                            ['name' => 'to', 'type' => 'string'],
                            ['name' => 'per_page', 'type' => 'integer', 'default' => 50, 'help' => 'Máx. 200.'],
                        ],
                    ],
                    [
                        'method' => 'GET', 'path' => '/api/v1/sms/messages/{uuid}', 'scope' => $scopes::READ,
                        'summary' => 'Detalle de un mensaje.',
                        'path_params' => [['name' => 'uuid', 'type' => 'string', 'required' => true, 'help' => 'UUID del mensaje.']],
                    ],
                    [
                        'method' => 'GET', 'path' => '/api/v1/sms/batches/{uuid}', 'scope' => $scopes::READ,
                        'summary' => 'Detalle de un lote.',
                        'path_params' => [['name' => 'uuid', 'type' => 'string', 'required' => true, 'help' => 'UUID del lote.']],
                    ],
                    [
                        'method' => 'GET', 'path' => '/api/v1/sms/batches/{uuid}/messages', 'scope' => $scopes::READ,
                        'summary' => 'Mensajes de un lote.',
                        'path_params' => [['name' => 'uuid', 'type' => 'string', 'required' => true, 'help' => 'UUID del lote.']],
                        'query' => [['name' => 'per_page', 'type' => 'integer', 'default' => 100, 'help' => 'Máx. 500.']],
                    ],
                    [
                        'method' => 'GET', 'path' => '/api/v1/sms/usage', 'scope' => $scopes::READ,
                        'summary' => 'Consumo diario en un rango (30 días por defecto).',
                        'query' => [
                            ['name' => 'from', 'type' => 'date'],
                            ['name' => 'to', 'type' => 'date'],
                        ],
                    ],
                ],
            ]];
        });
    }

    public function registerSchedule($schedule): void
    {
        // Lotes programados cuyo job ya corrió pero quedaron sin cerrar.
        $schedule->call(function () {
            \Aero\Sms\Models\Batch::whereIn('status', ['scheduled', 'running'])->get()->each->refreshStatus();
        })->everyFiveMinutes();
    }

    public function registerPermissions(): array
    {
        return [
            'aero.sms.use' => [
                'tab'   => 'SMS',
                'label' => 'Usar SMS: enviar, ver sus mensajes y lotes, plantillas propias y consumo',
            ],
            'aero.sms.superadmin' => [
                'tab'   => 'SMS',
                'label' => 'Administrar SMS: configuración, bajas globales y datos de todos los tenants',
            ],
        ];
    }

    public function registerNavigation(): array
    {
        return [
            'sms' => [
                'label'       => 'SMS',
                'url'         => Backend::url('aero/sms/messages'),
                'icon'        => 'icon-commenting',
                'iconSvg'     => null,
                'permissions' => ['aero.sms.use', 'aero.sms.superadmin'],
                'order'       => 520,
                'sideMenu'    => [
                    'compose'   => ['label' => 'Enviar', 'icon' => 'icon-paper-plane', 'url' => Backend::url('aero/sms/compose'), 'permissions' => ['aero.sms.use', 'aero.sms.superadmin']],
                    'messages'  => ['label' => 'Mensajes', 'icon' => 'icon-envelope', 'url' => Backend::url('aero/sms/messages'), 'permissions' => ['aero.sms.use', 'aero.sms.superadmin']],
                    'batches'   => ['label' => 'Lotes', 'icon' => 'icon-list', 'url' => Backend::url('aero/sms/batches'), 'permissions' => ['aero.sms.use', 'aero.sms.superadmin']],
                    'usage'     => ['label' => 'Consumo', 'icon' => 'icon-bar-chart', 'url' => Backend::url('aero/sms/usage'), 'permissions' => ['aero.sms.use', 'aero.sms.superadmin']],
                    'templates' => ['label' => 'Plantillas', 'icon' => 'icon-file-text-o', 'url' => Backend::url('aero/sms/templates'), 'permissions' => ['aero.sms.use', 'aero.sms.superadmin']],
                    'optouts'   => ['label' => 'Bajas', 'icon' => 'icon-ban', 'url' => Backend::url('aero/sms/optouts'), 'permissions' => ['aero.sms.superadmin']],
                ],
            ],
        ];
    }

    public function registerSettings(): array
    {
        return [
            'settings' => [
                'label'       => 'SMS',
                'description' => 'Proveedor de SMS, credenciales y límites de envío.',
                'category'    => 'Sistema',
                'icon'        => 'icon-commenting',
                'class'       => \Aero\Sms\Models\Settings::class,
                'order'       => 510,
                'permissions' => ['aero.sms.superadmin'],
            ],
        ];
    }
}
