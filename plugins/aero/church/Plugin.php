<?php namespace Aero\Church;

use Backend;
use System\Classes\PluginBase;

/**
 * Gestión de iglesias y congregaciones. Multiiglesia: cada iglesia es un Tenant de
 * Aero.Sites y puede tener varias sedes. Integraciones blandas (class_exists + eventos aero.church.*).
 */
class Plugin extends PluginBase
{
    public function pluginDetails(): array
    {
        return [
            'name'        => 'Iglesia',
            'description' => 'Miembros, familias, ministerios, eventos y aportes de iglesias y congregaciones.',
            'author'      => 'Aero',
            'icon'        => 'icon-institution',
        ];
    }

    public function boot(): void
    {
        $this->app['router']->group([], function () {
            require __DIR__ . '/routes.php';
        });

        // Conciliación de aportes por QR. Integración blanda: sin Pay, el evento no existe.
        \Event::listen('aero.church.eventChanged', fn ($event, $changed) => app(\Aero\Church\Classes\EventChanges::class)->handle($event, $changed));
        \Event::listen('aero.pay.qrStatusChanged', fn ($qr) => app(\Aero\Church\Classes\ContributionService::class)->onQrStatusChanged($qr));
    }

    public function register(): void
    {
        $this->registerConsoleCommand('church:remind-events', \Aero\Church\Console\RemindEvents::class);
        $this->registerConsoleCommand('church:birthdays', \Aero\Church\Console\SendBirthdays::class);
        $this->registerConsoleCommand('church:sync-contributions', \Aero\Church\Console\SyncContributions::class);
    }

    public function registerSchedule($schedule): void
    {
        $schedule->command('church:remind-events')->everyTenMinutes();
        $schedule->command('church:sync-contributions')->everyTenMinutes();
        $schedule->command('church:birthdays')->dailyAt('08:00');
    }

    public function registerComponents(): array
    {
        return [
            \Aero\Church\Components\ChurchPublic::class => 'churchPublic',
            \Aero\Church\Components\ChurchSite::class   => 'churchSite',
            \Aero\Church\Components\ChurchPortal::class => 'churchPortal',
        ];
    }

    public function registerPermissions(): array
    {
        return [
            'aero.church.use' => [
                'tab'   => 'Iglesia',
                'label' => 'Administrar la iglesia: sedes, miembros, familias y configuración',
            ],
            'aero.church.pastor' => [
                'tab'   => 'Iglesia',
                'label' => 'Pastor o dirección: consultar miembros, indicadores y reportes institucionales',
            ],
            'aero.church.secretary' => [
                'tab'   => 'Iglesia',
                'label' => 'Secretaría: registrar miembros, familias, visitantes y eventos',
            ],
            'aero.church.treasury' => [
                'tab'   => 'Iglesia',
                'label' => 'Tesorería: aportes, conciliaciones y reportes financieros',
            ],
            'aero.church.leader' => [
                'tab'   => 'Iglesia',
                'label' => 'Líder de ministerio: administrar los grupos y actividades a su cargo',
            ],
            'aero.church.superadmin' => [
                'tab'   => 'Iglesia',
                'label' => 'Administrar Iglesia: datos de todas las iglesias',
            ],
        ];
    }

    public function registerNavigation(): array
    {
        $all = ['aero.church.use', 'aero.church.pastor', 'aero.church.secretary', 'aero.church.superadmin'];
        $manage = ['aero.church.use', 'aero.church.superadmin'];
        $withLeader = array_merge($all, ['aero.church.leader']);
        $money = ['aero.church.use', 'aero.church.treasury', 'aero.church.superadmin'];
        $summary = ['aero.church.use', 'aero.church.treasury', 'aero.church.pastor', 'aero.church.superadmin'];
        $care = ['aero.church.use', 'aero.church.pastor', 'aero.church.secretary', 'aero.church.superadmin'];
        $prayers = ['aero.church.use', 'aero.church.pastor', 'aero.church.leader', 'aero.church.superadmin'];
        $top = array_unique(array_merge($withLeader, ['aero.church.treasury']));

        // Cada rol aterriza en la primera pantalla a la que sí tiene acceso.
        $user = \BackendAuth::getUser();
        $landing = 'groups';
        if ($user && !$user->hasAnyAccess($withLeader)) {
            $landing = 'contributions';
        }
        $item = fn (string $label, string $icon, string $path, array $p) => [
            'label' => $label, 'icon' => $icon, 'url' => Backend::url('aero/church/' . $path), 'permissions' => $p,
        ];

        return [
            'church' => [
                'label'       => 'Iglesia',
                'url'         => Backend::url('aero/church/' . $landing),
                'icon'        => 'icon-institution',
                'iconSvg'     => null,
                'permissions' => $top,
                'order'       => 532,
                'sideMenu'    => [
                    'members'  => $item('Miembros', 'icon-users', 'members', $all),
                    'families' => $item('Familias', 'icon-home', 'families', $all),
                    'events'   => $item('Eventos', 'icon-bullhorn', 'events', $all),
                    'groups'   => $item('Grupos', 'icon-sitemap', 'groups', $withLeader),
                    'meetings' => $item('Reuniones', 'icon-calendar', 'meetings', $withLeader),
                    'contributions' => $item('Aportes', 'icon-money', 'contributions', $money),
                    'funds'    => $item('Conceptos de aporte', 'icon-tags', 'funds', $money),
                    'giving'   => $item('Resumen de aportes', 'icon-bar-chart', 'giving', $summary),
                    'sermons'  => $item('Predicaciones', 'icon-microphone', 'sermons', $care),
                    'series'   => $item('Series', 'icon-list-ol', 'series', $care),
                    'resources' => $item('Recursos', 'icon-book', 'resources', $care),
                    'posts'    => $item('Noticias', 'icon-file-text', 'posts', $care),
                    'visits'   => $item('Visitantes', 'icon-user-plus', 'visits', $care),
                    'prayers'  => $item('Oración', 'icon-heart', 'prayers', $prayers),
                    'announcements' => $item('Comunicados', 'icon-comments', 'announcements', $care),
                    'givingsettings' => $item('Configuración', 'icon-cog', 'givingsettings', $money),
                    'branches' => $item('Sedes', 'icon-map-marker', 'branches', $manage),
                ],
            ],
        ];
    }
}
