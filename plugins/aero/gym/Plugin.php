<?php namespace Aero\Gym;

use Backend;
use Event;
use System\Classes\PluginBase;

/**
 * Rubro gimnasio: socios (sobre RainLab.User), membresías con vencimiento y
 * cobro por QR, control de acceso, clases con cupo y lista de espera,
 * rutinas, pantalla en vivo y reportes. Integraciones blandas con Pay, Hello,
 * Shop y Sites (class_exists + eventos aero.gym.*).
 */
class Plugin extends PluginBase
{
    public $require = ['RainLab.User'];

    public function pluginDetails(): array
    {
        return [
            'name'        => 'Gimnasio',
            'description' => 'Socios, membresías, acceso por QR, clases con cupo y lista de espera, rutinas y reportes.',
            'author'      => 'Aero',
            'icon'        => 'icon-heartbeat',
        ];
    }

    public function register(): void
    {
        $this->registerConsoleCommand('gym:generate-sessions', \Aero\Gym\Console\GenerateSessionsCommand::class);
        $this->registerConsoleCommand('gym:daily', \Aero\Gym\Console\DailyCommand::class);
    }

    public function registerComponents(): array
    {
        return [
            \Aero\Gym\Components\GymPortal::class => 'gymPortal',
            \Aero\Gym\Components\GymProgress::class => 'gymProgress',
        ];
    }

    /** Para los temas: {% if gym_enabled() %} (el dominio tiene gimnasio y está activo). */
    public function registerMarkupTags(): array
    {
        return ['functions' => ['gym_enabled' => function () {
            $tenant = \Aero\Sites\Models\Tenant::resolveFromDomain(request()->getHost());

            return $tenant && \Aero\Gym\Models\GymSettings::isEnabled($tenant->id)
                && \Aero\Gym\Components\GymPortal::siteHasGym($tenant->id);
        }]];
    }

    public function boot(): void
    {
        // Pago del QR de la membresía → activación inmediata (el schedule es el respaldo).
        if (class_exists(\Aero\Pay\Models\QrCode::class)) {
            \Aero\Pay\Models\QrCode::extend(function ($model) {
                $model->bindEvent('model.afterUpdate', function () use ($model) {
                    if ($model->wasChanged('status') && $model->status === 'paid'
                        && str_starts_with((string) $model->external_reference, 'gym-membresia-')) {
                        app(\Aero\Gym\Classes\MembershipService::class)->syncPayments();
                    }
                });
            });
        }

        // Avisos por WhatsApp de lo que le pasa a la reserva del socio.
        Event::listen('aero.gym.waitlistPromoted', function ($booking) {
            $s = $booking->session;
            if ($booking->member && $s) {
                \Aero\Gym\Classes\Notifier::whatsapp($booking->member,
                    "¡Se liberó un cupo! Quedaste inscrito en {$s->classType?->name} el {$s->starts_at->format('d/m H:i')}.");
            }
        });
        Event::listen('aero.gym.sessionCancelled', function ($session) {
            $session->bookings()->where('status', 'cancelled')->with('member')->get()->each(function ($b) use ($session) {
                if ($b->member) {
                    \Aero\Gym\Classes\Notifier::whatsapp($b->member,
                        "Se canceló la clase {$session->classType?->name} del {$session->starts_at->format('d/m H:i')}. Disculpa las molestias.");
                }
            });
        });
    }

    public function registerSchedule($schedule): void
    {
        $schedule->command('gym:generate-sessions')->dailyAt('02:00');
        $schedule->command('gym:daily')->dailyAt('09:00');
        $schedule->call(fn () => app(\Aero\Gym\Classes\MembershipService::class)->syncPayments())->everyFiveMinutes();
    }

    public function registerPermissions(): array
    {
        return [
            'aero.gym.use' => [
                'tab'   => 'Gimnasio',
                'label' => 'Usar el gimnasio: socios, membresías, clases, acceso, rutinas y reportes propios',
            ],
            'aero.gym.superadmin' => [
                'tab'   => 'Gimnasio',
                'label' => 'Administrar Gimnasio: datos de todos los tenants',
            ],
        ];
    }

    public function registerNavigation(): array
    {
        $p = ['aero.gym.use', 'aero.gym.superadmin'];
        $item = fn (string $label, string $icon, string $path) => ['label' => $label, 'icon' => $icon, 'url' => Backend::url('aero/gym/' . $path), 'permissions' => $p];

        $menu = [
            'gym' => [
                'label'       => 'Gimnasio',
                'url'         => Backend::url('aero/gym/members'),
                'icon'        => 'icon-heartbeat',
                'iconSvg'     => null,
                'permissions' => $p,
                'order'       => 522,
                'sideMenu'    => [
                    'live'        => $item('Clases en vivo', 'icon-tv', 'liveboard'),
                    'access'      => $item('Acceso', 'icon-qrcode', 'access'),
                    'members'     => $item('Socios', 'icon-users', 'members'),
                    'memberships' => $item('Membresías', 'icon-id-card', 'memberships'),
                    'plans'       => $item('Planes', 'icon-list', 'plans'),
                    'groups'      => $item('Grupos', 'icon-group', 'groups'),
                    'sessions'    => $item('Clases', 'icon-calendar', 'sessions'),
                    'schedules'   => $item('Horarios', 'icon-clock', 'schedules'),
                    'classtypes'  => $item('Tipos de clase', 'icon-bookmark', 'classtypes'),
                    'instructors' => $item('Instructores', 'icon-user', 'instructors'),
                    'routines'    => $item('Rutinas', 'icon-file-text', 'routines'),
                    'measurements' => $item('Medidas', 'icon-bar-chart', 'measurements'),
                    'accesslogs'  => $item('Ingresos', 'icon-sign-in', 'accesslogs'),
                    'reports'     => $item('Reportes', 'icon-bar-chart', 'reports'),
                    'settings'    => $item('Configuración', 'icon-cog', 'settings'),
                ],
            ],
        ];

        // Venta de suplementos y accesorios: se hace en Aero.Shop.
        if (class_exists(\Aero\Shop\Plugin::class)) {
            $menu['gym']['sideMenu']['shop'] = [
                'label' => 'Tienda', 'icon' => 'icon-shopping-cart',
                'url' => Backend::url('aero/shop/products'), 'permissions' => ['aero.shop.manage_products'],
            ];
        }

        return $menu;
    }
}
