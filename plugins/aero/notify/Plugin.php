<?php namespace Aero\Notify;

use Backend;
use System\Classes\PluginBase;

/**
 * Gateway de notificaciones omnicanal.
 *
 * Punto único de las notificaciones transaccionales del ecosistema: un catálogo
 * de eventos, reglas de entrega por audiencia y canal, y plantillas por canal e
 * idioma. Los plugins de negocio disparan eventos; aquí se decide a quién, por
 * dónde y con qué texto.
 *
 * Solo depende de Aero.Sites, que aporta el Tenant. Hello, Api, Crm, Qrbo y
 * Shop son opcionales y se detectan con class_exists(): el gateway tiene que
 * poder instalarse en un entorno donde falte cualquiera de ellos.
 */
class Plugin extends PluginBase
{
    public $require = ['Aero.Sites'];

    public function pluginDetails(): array
    {
        return [
            'name'        => 'Notify',
            'description' => 'Gateway omnicanal de notificaciones transaccionales',
            'author'      => 'Aero',
            'icon'        => 'icon-bell',
            'homepage'    => 'https://micro.clouds.com.bo',
        ];
    }

    public function register(): void
    {
        $this->registerConsoleCommand('notify.seed-events', \Aero\Notify\Console\SeedNotifyEvents::class);
        $this->registerConsoleCommand('notify.events', \Aero\Notify\Console\ListNotifyEvents::class);
    }

    /**
     * Drivers de canal por defecto. Registrados vía el mismo evento que
     * DriverManager expone para extenderse, así un consumidor externo puede
     * sumar un canal sin tocar esta clase.
     */
    public function boot(): void
    {
        \Event::listen('aero.notify.registerChannelDrivers', function ($manager) {
            $manager->register('email', \Aero\Notify\Classes\Drivers\EmailDriver::class);
            $manager->register('whatsapp', \Aero\Notify\Classes\Drivers\WhatsAppDriver::class);
        });
    }

    public function registerNavigation(): array
    {
        return [
            'notify' => [
                'label'       => 'Notificaciones',
                'url'         => Backend::url('aero/notify/events'),
                'icon'        => 'icon-bell',
                'permissions' => ['aero.notify.*'],
                'order'       => 220,

                'sideMenu' => [
                    'notify-events' => [
                        'label'       => 'Eventos',
                        'icon'        => 'icon-list-ul',
                        'url'         => Backend::url('aero/notify/events'),
                        'permissions' => ['aero.notify.view_events'],
                    ],
                    'notify-deliveries' => [
                        'label'       => 'Entregas',
                        'icon'        => 'icon-paper-plane',
                        'url'         => Backend::url('aero/notify/deliveries'),
                        'permissions' => ['aero.notify.view_deliveries'],
                    ],
                ],
            ],
        ];
    }

    public function registerPermissions(): array
    {
        return [
            // Todo el plugin es de la plataforma por ahora: el catálogo de
            // eventos es genérico para todos los tenants y todavía no existe
            // el motor de entrega ni la resolución de audiencias por tenant
            // (fase 2). Ninguno de estos permisos se concede a tenant_admin
            // (ver updates/grant_role_permissions.php); se revisa cuando el
            // resto del gateway esté construido.
            'aero.notify.manage_events' => [
                'tab'   => 'Notificaciones',
                'label' => 'Administrar el catálogo de eventos',
            ],
            'aero.notify.manage_global_rules' => [
                'tab'   => 'Notificaciones',
                'label' => 'Administrar las reglas globales de la plataforma',
            ],

            'aero.notify.view_events' => [
                'tab'   => 'Notificaciones',
                'label' => 'Ver el catálogo de eventos',
            ],
            'aero.notify.manage_rules' => [
                'tab'   => 'Notificaciones',
                'label' => 'Administrar las reglas de entrega del tenant',
            ],
            'aero.notify.manage_templates' => [
                'tab'   => 'Notificaciones',
                'label' => 'Administrar plantillas',
            ],
            'aero.notify.manage_channels' => [
                'tab'   => 'Notificaciones',
                'label' => 'Administrar canales',
            ],
            'aero.notify.view_deliveries' => [
                'tab'   => 'Notificaciones',
                'label' => 'Ver el registro de entregas',
            ],
            'aero.notify.resend' => [
                'tab'   => 'Notificaciones',
                'label' => 'Reenviar una entrega',
            ],
            'aero.notify.send_test' => [
                'tab'   => 'Notificaciones',
                'label' => 'Enviar notificaciones de prueba',
            ],
        ];
    }
}
