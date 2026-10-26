<?php namespace Aero\Realestate;

use Backend;
use System\Classes\PluginBase;

/**
 * Inmobiliaria para Bolivia: propiedades con ofertas de venta, alquiler y anticrético.
 * Multiempresa: cada inmobiliaria es un Tenant de Aero.Sites. Clientes, propietarios y
 * agentes se pueblan en RainLab.User. Integraciones blandas (class_exists + eventos aero.realestate.*).
 */
class Plugin extends PluginBase
{
    public $require = ['RainLab.User'];

    public function pluginDetails(): array
    {
        return [
            'name'        => 'Inmobiliaria',
            'description' => 'Propiedades en venta, alquiler y anticrético con clientes, propietarios y agentes.',
            'author'      => 'Aero',
            'icon'        => 'icon-building',
        ];
    }

    public function register(): void
    {
        $this->registerConsoleCommand('realestate:demo', \Aero\Realestate\Console\DemoCommand::class);
        $this->registerConsoleCommand('realestate:remind', \Aero\Realestate\Console\RemindCommand::class);
        $this->registerConsoleCommand('realestate:anticretico-alerts', \Aero\Realestate\Console\AnticreticoAlertsCommand::class);
    }

    public function registerSchedule($schedule): void
    {
        $schedule->command('realestate:anticretico-alerts')->dailyAt('08:00');
        $schedule->command('realestate:remind')->everyTenMinutes()->withoutOverlapping(10);
    }

    public function registerComponents(): array
    {
        return [\Aero\Realestate\Components\RealestatePortal::class => 'realestatePortal'];
    }

    /** Para los temas: {% if realestate_public() %} (el dominio tiene el portal de inmuebles publicado). */
    public function registerMarkupTags(): array
    {
        return ['functions' => ['realestate_public' => function () {
            $tenant = \Aero\Sites\Models\Tenant::resolveFromDomain(request()->getHost());

            return $tenant && \Aero\Realestate\Models\RealestateSettings::isPublic($tenant->id);
        }]];
    }

    public function registerPermissions(): array
    {
        return [
            'aero.realestate.use' => [
                'tab'   => 'Inmobiliaria',
                'label' => 'Administrar la inmobiliaria: propiedades, ofertas, clientes, propietarios y agentes',
            ],
            'aero.realestate.agent' => [
                'tab'   => 'Inmobiliaria',
                'label' => 'Agente: propiedades, ofertas, clientes y propietarios',
            ],
            'aero.realestate.superadmin' => [
                'tab'   => 'Inmobiliaria',
                'label' => 'Administrar Inmobiliaria: datos de todas las inmobiliarias y catálogo global',
            ],
        ];
    }

    public function registerNavigation(): array
    {
        $all = ['aero.realestate.use', 'aero.realestate.agent', 'aero.realestate.superadmin'];
        $manage = ['aero.realestate.use', 'aero.realestate.superadmin'];
        $item = fn (string $label, string $icon, string $path, array $p) => [
            'label' => $label, 'icon' => $icon, 'url' => Backend::url('aero/realestate/' . $path), 'permissions' => $p,
        ];

        return [
            'realestate' => [
                'label'       => 'Inmobiliaria',
                'url'         => Backend::url('aero/realestate/dashboard'),
                'icon'        => 'icon-building',
                'iconSvg'     => null,
                'permissions' => $all,
                'order'       => 529,
                'sideMenu'    => [
                    'dashboard'  => $item('Resumen', 'icon-bar-chart', 'dashboard', $all),
                    'pipeline'   => $item('Embudo', 'icon-filter', 'pipeline', $all),
                    'leads'      => $item('Oportunidades', 'icon-bullseye', 'leads', $all),
                    'visits'     => $item('Visitas', 'icon-calendar', 'visits', $all),
                    'agreements' => $item('Contratos legales', 'icon-file-text', 'agreements', $all),
                    'documents'  => $item('Documentos', 'icon-folder-open', 'documents', $all),
                    'tracking'   => $item('Seguimiento anticréticos', 'icon-bar-chart', 'tracking', $all),
                    'contracts'  => $item('Anticréticos', 'icon-file-text', 'contracts', $all),
                    'properties' => $item('Propiedades', 'icon-building', 'properties', $all),
                    'offers'     => $item('Ofertas', 'icon-tags', 'offers', $all),
                    'clients'    => $item('Clientes', 'icon-users', 'clients', $all),
                    'owners'     => $item('Propietarios', 'icon-user', 'owners', $all),
                    'agents'     => $item('Agentes', 'icon-user', 'agents', $manage),
                    'locations'  => $item('Ubicaciones', 'icon-map', 'locations', $manage),
                    'features'   => $item('Características', 'icon-list', 'features', $manage),
                    'templates'  => $item('Plantillas de contrato', 'icon-file-text', 'templates', $manage),
                    'commissions'     => $item('Comisiones', 'icon-money', 'commissions', $manage),
                    'commissionrules' => $item('Reglas de comisión', 'icon-percent', 'commissionrules', $manage),
                    'reports'    => $item('Reportes', 'icon-table', 'reports', $manage),
                    'settings'   => $item('Configuración', 'icon-cog', 'settings', $manage),
                ],
            ],
        ];
    }
}
