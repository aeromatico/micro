<?php namespace Aero\Office;

use Backend;
use System\Classes\PluginBase;

/**
 * Reservas genéricas por fecha y hora para cualquier rubro (consultorios,
 * veterinarias, barberías, academias…). Multiempresa: el negocio es el
 * Tenant de Aero.Sites. Clientes opcionalmente sobre RainLab.User.
 * Integraciones blandas (class_exists + eventos aero.office.*): CRM, Hello, Pay.
 */
class Plugin extends PluginBase
{
    public $require = ['RainLab.User'];

    public function pluginDetails(): array
    {
        return [
            'name'        => 'Oficina',
            'description' => 'Reservas de citas y servicios para cualquier negocio: sucursales, profesionales, agenda y portal público.',
            'author'      => 'Aero',
            'icon'        => 'icon-calendar',
        ];
    }

    public function register(): void
    {
        $this->registerConsoleCommand('office:demo', \Aero\Office\Console\DemoCommand::class);
        $this->registerConsoleCommand('office:remind', \Aero\Office\Console\RemindCommand::class);
    }

    public function boot(): void
    {
        // Avisos por Aero.Notify (WhatsApp vía Hello / correo). Solo lo que el cliente no inició le llega al cliente.
        \Event::listen('aero.office.bookingCreated', function ($b) {
            if ($b->source === 'public') {
                \Aero\Office\Classes\Notifier::fire('office.booking.created', $b);
                if ($b->status === 'pending') {
                    \Aero\Office\Classes\Notifier::fire('office.booking.received', $b);
                }
            }
        });
        \Event::listen('aero.office.bookingConfirmed', fn ($b) => \Aero\Office\Classes\Notifier::fire('office.booking.confirmed', $b));
        \Event::listen('aero.office.bookingRejected', fn ($b) => \Aero\Office\Classes\Notifier::fire('office.booking.rejected', $b, ['reason' => (string) $b->cancel_reason]));
        \Event::listen('aero.office.bookingCancelled', function ($b) {
            $byCustomer = $b->logs()->where('action', 'cancelled')->orderByDesc('id')->value('actor') === 'customer';
            \Aero\Office\Classes\Notifier::fire($byCustomer ? 'office.booking.cancelled_by_customer' : 'office.booking.cancelled', $b);
        });
        \Event::listen('aero.office.bookingRescheduled', function ($b) {
            $byCustomer = $b->logs()->whereIn('action', ['rescheduled', 'reassigned'])->orderByDesc('id')->value('actor') === 'customer';
            \Aero\Office\Classes\Notifier::fire($byCustomer ? 'office.booking.rescheduled_by_customer' : 'office.booking.rescheduled', $b);
        });
    }

    public function registerSchedule($schedule): void
    {
        $schedule->command('office:remind')->everyTenMinutes();
    }

    public function registerComponents(): array
    {
        return [
            \Aero\Office\Components\OfficeBooking::class => 'officeBooking',
            \Aero\Office\Components\OfficeAccount::class => 'officeAccount',
        ];
    }

    /** Para los temas: {% if office_public() %} (el dominio tiene reservas publicadas). */
    public function registerMarkupTags(): array
    {
        return ['functions' => ['office_public' => function () {
            $tenant = \Aero\Sites\Models\Tenant::resolveFromDomain(request()->getHost());

            return $tenant && \Aero\Office\Models\OfficeSettings::isPublic($tenant->id);
        }]];
    }

    public function registerPermissions(): array
    {
        return [
            'aero.office.use' => [
                'tab'   => 'Oficina',
                'label' => 'Administrar el negocio: sucursales, servicios, profesionales, horarios, clientes y reservas',
            ],
            'aero.office.settings' => [
                'tab'   => 'Oficina',
                'label' => 'Configuración general del negocio y portal público (propietario)',
            ],
            'aero.office.reception' => [
                'tab'   => 'Oficina',
                'label' => 'Recepción: clientes, citas y agenda',
            ],
            'aero.office.professional' => [
                'tab'   => 'Oficina',
                'label' => 'Profesional: consultar sus propias reservas y atenciones',
            ],
            'aero.office.superadmin' => [
                'tab'   => 'Oficina',
                'label' => 'Administrar Oficina: datos de todos los negocios',
            ],
        ];
    }

    public function registerNavigation(): array
    {
        $all = ['aero.office.use', 'aero.office.reception', 'aero.office.professional', 'aero.office.superadmin'];
        $front = ['aero.office.use', 'aero.office.reception', 'aero.office.superadmin'];
        $manage = ['aero.office.use', 'aero.office.superadmin'];
        $item = fn (string $label, string $icon, string $path, array $p) => [
            'label' => $label, 'icon' => $icon, 'url' => Backend::url('aero/office/' . $path), 'permissions' => $p,
        ];

        return [
            'office' => [
                'label'       => 'Reservas',
                'url'         => Backend::url('aero/office/panel'),
                'icon'        => 'icon-calendar',
                'iconSvg'     => null,
                'permissions' => $all,
                'order'       => 524,
                'sideMenu'    => [
                    'panel'     => $item('Resumen', 'icon-bar-chart', 'panel', $all),
                    'agenda'    => $item('Agenda', 'icon-calendar', 'agenda', $all),
                    'bookings'  => $item('Reservas', 'icon-list', 'bookings', $all),
                    'customers' => $item('Clientes', 'icon-users', 'customers', $front),
                    'services'  => $item('Servicios', 'icon-tags', 'services', $manage),
                    'workers'   => $item('Profesionales', 'icon-user', 'workers', $manage),
                    'branches'  => $item('Sucursales', 'icon-building', 'branches', $manage),
                    'timeoffs'  => $item('Ausencias y feriados', 'icon-clock', 'timeoffs', $manage),
                    'settings'  => $item('Configuración', 'icon-cog', 'settings', ['aero.office.settings', 'aero.office.superadmin']),
                ],
            ],
        ];
    }
}
