<?php namespace Aero\Tracking;

use Backend;
use Event;
use System\Classes\PluginBase;

/**
 * Seguimiento de flotas y trabajos en movimiento. Independiente y genérico:
 * un Asset (persona, vehículo, lo que sea) recorre las Stops de un Job. Lo que
 * el Job represente —pedido de restaurante, carga, traslado— lo decide quien lo
 * crea vía API; external_type/external_id enlazan con otros plugins.
 */
class Plugin extends PluginBase
{
    /** El middleware `aero.api` y la atribución por key viven en aero/api. */
    public $require = ['Aero.Api'];

    public function pluginDetails(): array
    {
        return [
            'name'        => 'Tracking',
            'description' => 'Seguimiento en tiempo real de flotas, personas y trabajos con API propia.',
            'author'      => 'Aero',
            'icon'        => 'icon-truck',
        ];
    }

    public function register(): void
    {
        $this->registerConsoleCommand('aero.tracking.prune', \Aero\Tracking\Console\PrunePositions::class);
    }

    public function boot(): void
    {
        $this->app['router']->group([], function () {
            require __DIR__ . '/routes.php';
        });

        Event::listen('aero.api.registerScopes', function () {
            return ['tracking' => [
                'label'  => 'Tracking',
                'scopes' => \Aero\Tracking\Classes\Api\Scopes::all(),
            ]];
        });

        if (!class_exists(\Aero\Api\Classes\EndpointRegistry::class)) {
            return;
        }

        Event::listen('aero.api.registerEndpoints', function () {
            $scopes = \Aero\Tracking\Classes\Api\Scopes::class;

            return ['tracking' => [
                'label' => 'Tracking',
                'endpoints' => [
                    [
                        'method' => 'GET', 'path' => '/api/v1/tracking/assets', 'scope' => $scopes::ASSETS_READ,
                        'summary' => 'Lista de activos (vehículos, personas).',
                        'query' => [
                            ['name' => 'type', 'type' => 'string'],
                            ['name' => 'code', 'type' => 'string'],
                            ['name' => 'active', 'type' => 'boolean'],
                            ['name' => 'per_page', 'type' => 'integer', 'default' => 50, 'help' => 'Máx. 200.'],
                        ],
                    ],
                    [
                        'method' => 'GET', 'path' => '/api/v1/tracking/assets/{id}', 'scope' => $scopes::ASSETS_READ,
                        'summary' => 'Detalle de un activo (incluye su token de ingesta).',
                        'path_params' => [['name' => 'id', 'type' => 'integer', 'required' => true]],
                    ],
                    [
                        'method' => 'POST', 'path' => '/api/v1/tracking/assets', 'scope' => $scopes::ASSETS_WRITE,
                        'summary' => 'Crea un activo.',
                        'body_example' => "{\n    \"name\": \"Moto 1\",\n    \"type\": \"vehicle\",\n    \"code\": \"M-01\"\n}",
                    ],
                    [
                        'method' => 'PATCH', 'path' => '/api/v1/tracking/assets/{id}', 'scope' => $scopes::ASSETS_WRITE,
                        'summary' => 'Actualiza un activo.',
                        'path_params' => [['name' => 'id', 'type' => 'integer', 'required' => true]],
                        'body_example' => "{\n    \"name\": \"Moto 1 (repintada)\",\n    \"is_active\": true\n}",
                    ],
                    [
                        'method' => 'DELETE', 'path' => '/api/v1/tracking/assets/{id}', 'scope' => $scopes::ASSETS_WRITE,
                        'summary' => 'Elimina un activo.',
                        'path_params' => [['name' => 'id', 'type' => 'integer', 'required' => true]],
                    ],
                    [
                        'method' => 'POST', 'path' => '/api/v1/tracking/assets/{id}/regenerate-token', 'scope' => $scopes::ASSETS_WRITE,
                        'summary' => 'Regenera el token de ingesta OwnTracks del activo.',
                        'path_params' => [['name' => 'id', 'type' => 'integer', 'required' => true]],
                    ],
                    [
                        'method' => 'GET', 'path' => '/api/v1/tracking/jobs', 'scope' => $scopes::JOBS_READ,
                        'summary' => 'Lista de trabajos.',
                        'query' => [
                            ['name' => 'status', 'type' => 'string'],
                            ['name' => 'asset_id', 'type' => 'integer'],
                            ['name' => 'reference', 'type' => 'string'],
                            ['name' => 'open', 'type' => 'boolean', 'help' => 'Solo trabajos abiertos.'],
                            ['name' => 'per_page', 'type' => 'integer', 'default' => 50, 'help' => 'Máx. 200.'],
                        ],
                    ],
                    [
                        'method' => 'GET', 'path' => '/api/v1/tracking/jobs/{uuid}', 'scope' => $scopes::JOBS_READ,
                        'summary' => 'Detalle de un trabajo, con sus paradas.',
                        'path_params' => [['name' => 'uuid', 'type' => 'string', 'required' => true]],
                    ],
                    [
                        'method' => 'POST', 'path' => '/api/v1/tracking/jobs', 'scope' => $scopes::JOBS_WRITE,
                        'summary' => 'Crea un trabajo con sus paradas.',
                        'body_example' => "{\n    \"title\": \"Entrega #123\",\n    \"stops\": [\n        {\"name\": \"Recojo\", \"lat\": -17.78, \"lng\": -63.18},\n        {\"name\": \"Entrega\", \"lat\": -17.79, \"lng\": -63.19}\n    ]\n}",
                    ],
                    [
                        'method' => 'POST', 'path' => '/api/v1/tracking/jobs/{uuid}/assign', 'scope' => $scopes::JOBS_WRITE,
                        'summary' => 'Asigna (o desasigna con null) un activo al trabajo.',
                        'path_params' => [['name' => 'uuid', 'type' => 'string', 'required' => true]],
                        'body_example' => "{\n    \"asset_id\": 1\n}",
                    ],
                    [
                        'method' => 'POST', 'path' => '/api/v1/tracking/jobs/{uuid}/status', 'scope' => $scopes::JOBS_WRITE,
                        'summary' => 'Cambia el estado del trabajo.',
                        'path_params' => [['name' => 'uuid', 'type' => 'string', 'required' => true]],
                        'body_example' => "{\n    \"status\": \"in_progress\"\n}",
                    ],
                    [
                        'method' => 'POST', 'path' => '/api/v1/tracking/jobs/{uuid}/stops/{stopId}/status', 'scope' => $scopes::JOBS_WRITE,
                        'summary' => 'Cambia el estado de una parada.',
                        'path_params' => [
                            ['name' => 'uuid', 'type' => 'string', 'required' => true],
                            ['name' => 'stopId', 'type' => 'integer', 'required' => true],
                        ],
                        'body_example' => "{\n    \"status\": \"done\"\n}",
                    ],
                    [
                        'method' => 'GET', 'path' => '/api/v1/tracking/tracking/live', 'scope' => $scopes::TRACKING_READ,
                        'summary' => 'Última posición de cada activo activo, con su trabajo abierto.',
                    ],
                    [
                        'method' => 'GET', 'path' => '/api/v1/tracking/assets/{id}/positions', 'scope' => $scopes::TRACKING_READ,
                        'summary' => 'Historial de posiciones de un activo.',
                        'path_params' => [['name' => 'id', 'type' => 'integer', 'required' => true]],
                        'query' => [
                            ['name' => 'from', 'type' => 'date'],
                            ['name' => 'to', 'type' => 'date'],
                            ['name' => 'limit', 'type' => 'integer', 'default' => 2000, 'help' => 'Máx. 5000.'],
                        ],
                    ],
                    [
                        'method' => 'POST', 'path' => '/api/v1/tracking/assets/{id}/position', 'scope' => $scopes::TRACKING_WRITE,
                        'summary' => 'Empuja una posición del activo (para trackers propios).',
                        'path_params' => [['name' => 'id', 'type' => 'integer', 'required' => true]],
                        'body_example' => "{\n    \"lat\": -17.78,\n    \"lng\": -63.18,\n    \"speed\": 12.5\n}",
                    ],
                ],
            ]];
        });
    }

    public function registerSchedule($schedule): void
    {
        $schedule->command('aero.tracking:prune')->dailyAt('03:30');
    }

    public function registerPermissions(): array
    {
        return [
            'aero.tracking.use' => [
                'tab'   => 'Tracking',
                'label' => 'Usar Tracking: mapa en vivo, activos y trabajos propios',
            ],
            'aero.tracking.superadmin' => [
                'tab'   => 'Tracking',
                'label' => 'Administrar Tracking: datos de todos los tenants',
            ],
        ];
    }

    public function registerNavigation(): array
    {
        $perms = ['aero.tracking.use', 'aero.tracking.superadmin'];

        return [
            'tracking' => [
                'label'       => 'Tracking',
                'url'         => Backend::url('aero/tracking/livemap'),
                'icon'        => 'icon-truck',
                'iconSvg'     => null,
                'permissions' => $perms,
                'order'       => 530,
                'sideMenu'    => [
                    'livemap' => ['label' => 'Mapa en vivo', 'icon' => 'icon-map-marker', 'url' => Backend::url('aero/tracking/livemap'), 'permissions' => $perms],
                    'jobs'    => ['label' => 'Trabajos', 'icon' => 'icon-list', 'url' => Backend::url('aero/tracking/jobs'), 'permissions' => $perms],
                    'assets'  => ['label' => 'Activos', 'icon' => 'icon-truck', 'url' => Backend::url('aero/tracking/assets'), 'permissions' => $perms],
                ],
            ],
        ];
    }
}
