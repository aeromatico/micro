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

        // API REST y webhooks de Aero.Api: solo si está instalado; nunca es una dependencia dura.
        if (class_exists(\Aero\Api\Classes\EndpointRegistry::class)) {
            \Event::listen('aero.api.registerScopes', fn () => ['church' => ['label' => 'Iglesia', 'scopes' => \Aero\Church\Classes\Api\Scopes::all()]]);

            \Event::listen('aero.api.registerEndpoints', fn () => ['church' => [
                'label' => 'Iglesia',
                'endpoints' => [
                    [
                        'method' => 'GET', 'path' => '/api/v1/church/members', 'scope' => \Aero\Church\Classes\Api\Scopes::MEMBERS_READ,
                        'summary' => 'Lista de miembros (datos básicos). El contacto solo de adultos que aceptaron mensajes.',
                        'query' => [
                            ['name' => 'q', 'type' => 'string', 'help' => 'Nombre o apellido.'],
                            ['name' => 'status', 'type' => 'string', 'help' => 'visitor, new, active o inactive.'],
                            ['name' => 'branch_id', 'type' => 'integer'],
                            ['name' => 'per_page', 'type' => 'integer', 'default' => 50, 'help' => 'Máx. 200.'],
                        ],
                    ],
                    [
                        'method' => 'GET', 'path' => '/api/v1/church/members/{id}', 'scope' => \Aero\Church\Classes\Api\Scopes::MEMBERS_READ,
                        'summary' => 'Detalle básico de un miembro.',
                        'path_params' => [['name' => 'id', 'type' => 'integer', 'required' => true, 'help' => 'Id del miembro.']],
                    ],
                    [
                        'method' => 'GET', 'path' => '/api/v1/church/events', 'scope' => \Aero\Church\Classes\Api\Scopes::EVENTS_READ,
                        'summary' => 'Eventos de la iglesia con cupo e inscritos.',
                        'query' => [
                            ['name' => 'upcoming', 'type' => 'boolean', 'help' => 'Solo próximos y programados.'],
                            ['name' => 'public', 'type' => 'boolean', 'help' => 'Solo los mostrados en el sitio público.'],
                            ['name' => 'per_page', 'type' => 'integer', 'default' => 50, 'help' => 'Máx. 200.'],
                        ],
                    ],
                    [
                        'method' => 'GET', 'path' => '/api/v1/church/events/{id}', 'scope' => \Aero\Church\Classes\Api\Scopes::EVENTS_READ,
                        'summary' => 'Detalle de un evento.',
                        'path_params' => [['name' => 'id', 'type' => 'integer', 'required' => true, 'help' => 'Id del evento.']],
                    ],
                    [
                        'method' => 'GET', 'path' => '/api/v1/church/visits', 'scope' => \Aero\Church\Classes\Api\Scopes::VISITS_READ,
                        'summary' => 'Visitantes y su estado de seguimiento (sin datos de contacto).',
                        'query' => [['name' => 'status', 'type' => 'string', 'help' => 'new, contacted, invited, integrated o closed.'], ['name' => 'per_page', 'type' => 'integer', 'default' => 50]],
                    ],
                    [
                        'method' => 'POST', 'path' => '/api/v1/church/visits', 'scope' => \Aero\Church\Classes\Api\Scopes::VISITS_WRITE,
                        'summary' => 'Registra un visitante desde un formulario o chatbot externo (exige consent_data y un teléfono o correo).',
                        'body_example' => "{\n    \"name\": \"María López\",\n    \"phone\": \"+59170000000\",\n    \"interest\": \"Quiero conocer el grupo de jóvenes\",\n    \"consent_data\": true,\n    \"consent_messages\": true\n}",
                    ],
                ],
            ]]);
        }

        // Conciliación de aportes por QR. Integración blanda: sin Pay, el evento no existe.
        \Event::listen('aero.church.visitRegistered', fn ($v) => \Aero\Church\Classes\Notifier::visitRegistered($v));
        \Event::listen('aero.church.prayerRequested', fn ($p) => \Aero\Church\Classes\Notifier::prayerRequested($p));
        \Event::listen('aero.church.eventChanged', fn ($event, $changed) => app(\Aero\Church\Classes\EventChanges::class)->handle($event, $changed));
        \Event::listen('aero.pay.qrStatusChanged', fn ($qr) => app(\Aero\Church\Classes\ContributionService::class)->onQrStatusChanged($qr));
    }

    public function register(): void
    {
        $this->registerConsoleCommand('church:remind-events', \Aero\Church\Console\RemindEvents::class);
        $this->registerConsoleCommand('church:demo', \Aero\Church\Console\DemoCommand::class);
        $this->registerConsoleCommand('church:birthdays', \Aero\Church\Console\SendBirthdays::class);
        $this->registerConsoleCommand('church:sync-contributions', \Aero\Church\Console\SyncContributions::class);
    }

    public function registerSchedule($schedule): void
    {
        $schedule->command('church:remind-events')->everyTenMinutes()->withoutOverlapping(10);
        $schedule->command('church:sync-contributions')->everyTenMinutes()->withoutOverlapping(10);
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
        $landing = 'dashboard';
        if ($user && !$user->hasAnyAccess($care)) {
            $landing = $user->hasAnyAccess($withLeader) ? 'groups' : 'contributions';
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
                    'dashboard' => $item('Resumen', 'icon-bar-chart', 'dashboard', $care),
                    'reports'  => $item('Reportes', 'icon-table', 'reports', array_merge($care, ['aero.church.treasury'])),
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
                    'auditlog' => $item('Auditoría', 'icon-history', 'auditlog', ['aero.church.use', 'aero.church.pastor', 'aero.church.superadmin']),
                    'givingsettings' => $item('Configuración', 'icon-cog', 'givingsettings', $money),
                    'branches' => $item('Sedes', 'icon-map-marker', 'branches', $manage),
                ],
            ],
        ];
    }
}
