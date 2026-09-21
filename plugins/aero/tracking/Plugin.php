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
