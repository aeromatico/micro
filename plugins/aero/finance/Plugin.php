<?php namespace Aero\Finance;

use Backend;
use System\Classes\PluginBase;

/**
 * Finanzas: ingresos y egresos con libro diario/mayor de partida doble
 * (Bolivia, bolivianos). Shop y Gimnasio registran sus cobros por eventos
 * (class_exists + aero.*); sin ellos el plugin funciona con carga manual.
 */
class Plugin extends PluginBase
{
    public function pluginDetails(): array
    {
        return [
            'name'        => 'Finanzas',
            'description' => 'Ingresos y egresos con libro diario, libro mayor y balance de sumas y saldos.',
            'author'      => 'Aero',
            'icon'        => 'icon-bar-chart',
        ];
    }

    public function boot(): void
    {
        \Aero\Finance\Classes\Listeners::register();
    }

    public function registerPermissions(): array
    {
        return [
            'aero.finance.use' => [
                'tab'   => 'Finanzas',
                'label' => 'Usar Finanzas: ingresos, egresos, libros y reportes propios',
            ],
            'aero.finance.superadmin' => [
                'tab'   => 'Finanzas',
                'label' => 'Administrar Finanzas: datos de todos los tenants',
            ],
        ];
    }

    public function registerNavigation(): array
    {
        $p = ['aero.finance.use', 'aero.finance.superadmin'];
        $item = fn (string $label, string $icon, string $path) => ['label' => $label, 'icon' => $icon, 'url' => Backend::url('aero/finance/' . $path), 'permissions' => $p];

        return [
            'finance' => [
                'label'       => 'Finanzas',
                'url'         => Backend::url('aero/finance/movements'),
                'icon'        => 'icon-bar-chart',
                'iconSvg'     => null,
                'permissions' => $p,
                'order'       => 523,
                'sideMenu'    => [
                    'movements' => $item('Ingresos y egresos', 'icon-list', 'movements'),
                    'entries'   => $item('Libro diario', 'icon-file-text', 'entries'),
                    'reports'   => $item('Libro mayor y reportes', 'icon-bar-chart', 'reports'),
                    'accounts'  => $item('Plan de cuentas', 'icon-sitemap', 'accounts'),
                    'settings'  => $item('Configuración', 'icon-cog', 'settings'),
                ],
            ],
        ];
    }
}
