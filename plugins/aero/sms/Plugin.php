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
