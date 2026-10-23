<?php namespace Aero\Medical;

use Backend;
use System\Classes\PluginBase;

/**
 * Historia clínica electrónica por negocio (tenant), humana o veterinaria: el
 * núcleo clínico es común y el modo (Configuración) fija vocabulario, campos y
 * módulos. Acoplamiento blando con Aero.Office (clientes/tutores, profesionales,
 * reservas) por class_exists() y eventos aero.*. Datos de salud: cifrados,
 * auditados y nunca en eventos hacia webhooks.
 */
class Plugin extends PluginBase
{
    public function pluginDetails(): array
    {
        return [
            'name'        => 'Historia clínica',
            'description' => 'Historia clínica electrónica para consultorios y veterinarias: pacientes, consultas, recetas y seguimiento.',
            'author'      => 'Aero',
            'icon'        => 'icon-heartbeat',
        ];
    }

    public function register(): void
    {
        $this->registerConsoleCommand('medical:import-cie10', \Aero\Medical\Console\ImportCie10::class);
        $this->registerConsoleCommand('medical:import-liname', \Aero\Medical\Console\ImportLiname::class);
        $this->registerConsoleCommand('medical:remind', \Aero\Medical\Console\RemindCommand::class);
    }

    public function boot(): void
    {
        // Aero.Sheets (si está) puede ofrecer los pacientes como fuente de importación; solo anuncia, no depende.
        \Event::listen('aero.sheets.registerModels', fn () => [['model' => \Aero\Medical\Models\Patient::class, 'label' => 'Pacientes del consultorio']]);

        // API de pacientes (solo datos administrativos): únicamente si Aero.Api está instalado.
        if (class_exists(\Aero\Api\Classes\EndpointRegistry::class)) {
            $this->app['router']->group([], function () {
                require __DIR__ . '/routes.php';
            });

            \Event::listen('aero.api.registerScopes', fn () => ['medical' => ['label' => 'Historia clínica', 'scopes' => \Aero\Medical\Classes\Api\Scopes::all()]]);

            \Event::listen('aero.api.registerEndpoints', fn () => ['medical' => [
                'label' => 'Historia clínica',
                'endpoints' => [
                    [
                        'method' => 'GET', 'path' => '/api/v1/medical/patients', 'scope' => \Aero\Medical\Classes\Api\Scopes::PATIENTS_READ,
                        'summary' => 'Lista de pacientes (solo datos administrativos; nunca contenido clínico). Requiere la key del negocio.',
                        'query' => [
                            ['name' => 'q', 'type' => 'string', 'help' => 'Nombre, documento o microchip.'],
                            ['name' => 'kind', 'type' => 'string', 'help' => 'human o animal.'],
                            ['name' => 'active', 'type' => 'boolean'],
                            ['name' => 'per_page', 'type' => 'integer', 'default' => 50, 'help' => 'Máx. 200.'],
                        ],
                    ],
                    [
                        'method' => 'GET', 'path' => '/api/v1/medical/patients/{id}', 'scope' => \Aero\Medical\Classes\Api\Scopes::PATIENTS_READ,
                        'summary' => 'Detalle administrativo de un paciente.',
                        'path_params' => [['name' => 'id', 'type' => 'integer', 'required' => true, 'help' => 'Id del paciente.']],
                    ],
                ],
            ]]);
        }

        // Conciliación de cobros por QR: Aero.Pay avisa el cambio de estado (solo actúa sobre sus propios QR).
        \Event::listen('aero.pay.qrStatusChanged', [\Aero\Medical\Classes\ChargeService::class, 'onQrStatus']);

        // Botón en la reserva de Office (punto de extensión mínimo en su vista): solo personal clínico.
        \Event::listen('aero.office.bookingDetailActions', function ($booking) {
            $user = \BackendAuth::getUser();
            if (!$user || !$user->hasAccess(['aero.medical.professional', 'aero.medical.superadmin'], false)
                || !in_array($booking->status, ['confirmed', 'in_service', 'completed'], true)) {
                return null;
            }

            $note = \Aero\Medical\Models\Consultation::where('tenant_id', $booking->tenant_id)->where('booking_id', $booking->id)->first();

            return $note
                ? '<a class="btn btn-primary" href="' . e(Backend::url('aero/medical/consultations/update/' . $note->id)) . '">Abrir la nota clínica</a>'
                : '<a class="btn btn-primary" href="' . e(Backend::url('aero/medical/today/booking/' . $booking->id)) . '">Iniciar consulta</a>';
        });
    }

    public function registerSchedule($schedule): void
    {
        $schedule->command('medical:remind')->dailyAt('09:00');
    }

    public function registerPermissions(): array
    {
        return [
            'aero.medical.use' => [
                'tab'   => 'Historia clínica',
                'label' => 'Administrar el módulo: pacientes (datos administrativos) y bitácora',
            ],
            'aero.medical.settings' => [
                'tab'   => 'Historia clínica',
                'label' => 'Configuración del módulo (modo humano/animal, plantillas) y bitácora (propietario)',
            ],
            'aero.medical.professional' => [
                'tab'   => 'Historia clínica',
                'label' => 'Profesional: ver el contenido clínico, escribir y firmar notas',
            ],
            'aero.medical.reception' => [
                'tab'   => 'Historia clínica',
                'label' => 'Recepción: datos administrativos de pacientes, sin contenido clínico',
            ],
            'aero.medical.superadmin' => [
                'tab'   => 'Historia clínica',
                'label' => 'Administrar Historia clínica: datos de todos los negocios',
            ],
        ];
    }

    public function registerNavigation(): array
    {
        $all = ['aero.medical.use', 'aero.medical.reception', 'aero.medical.professional', 'aero.medical.superadmin'];
        $clinical = ['aero.medical.professional', 'aero.medical.superadmin'];
        $catalog = ['aero.medical.professional', 'aero.medical.settings', 'aero.medical.superadmin'];
        $owner = ['aero.medical.settings', 'aero.medical.superadmin'];
        $grooming = ['aero.medical.professional', 'aero.medical.reception', 'aero.medical.use', 'aero.medical.settings', 'aero.medical.superadmin'];
        $item = fn (string $label, string $icon, string $path, array $p) => [
            'label' => $label, 'icon' => $icon, 'url' => Backend::url('aero/medical/' . $path), 'permissions' => $p,
        ];

        // Peluquería y spa existe solo en modo animal (un negocio sin modo elegido o humano no lo ve).
        $animal = true;
        try {
            $tenantId = \Aero\Medical\Classes\CurrentTenant::id();
            $animal = !$tenantId || \Aero\Medical\Classes\CurrentTenant::isAdmin() || \Aero\Medical\Classes\Mode::forTenant($tenantId) === \Aero\Medical\Classes\Mode::ANIMAL;
        } catch (\Throwable $e) {
            // sin contexto de panel (consola, pruebas): se declara el ítem
        }

        $menu = [
            'medical' => [
                'label'       => 'Historia clínica',
                'url'         => Backend::url('aero/medical/patients'),
                'icon'        => 'icon-heartbeat',
                'iconSvg'     => null,
                'permissions' => $all,
                'order'       => 526,
                'sideMenu'    => [
                    'today'     => $item('Agenda de hoy', 'icon-calendar', 'today', $clinical),
                    'patients'  => $item('Pacientes', 'icon-users', 'patients', $all),
                    'consultations' => $item('Consultas', 'icon-heartbeat', 'consultations', $clinical),
                    'signature' => $item('Mi firma', 'icon-pencil', 'signature', $clinical),
                    'notetemplates' => $item('Plantillas de nota', 'icon-file-text', 'notetemplates', $owner),
                    'diagnosiscatalogs' => $item('Diagnósticos del negocio', 'icon-tags', 'diagnosiscatalogs', $catalog),
                    'medications' => $item('Medicamentos', 'icon-tags', 'medications', $catalog),
                    'examcatalogs' => $item('Exámenes', 'icon-tags', 'examcatalogs', $catalog),
                    'analytes' => $item('Analitos', 'icon-tags', 'analytes', $catalog),
                    'questionnaires' => $item('Cuestionarios', 'icon-file-text', 'questionnaires', $catalog),
                    'ranges' => $item('Rangos alto/bajo', 'icon-bar-chart', 'ranges', $catalog),
                    'vaccines' => $item('Vacunas', 'icon-tags', 'vaccines', $catalog),
                    'documenttemplates' => $item('Formatos de documentos', 'icon-file-text', 'documenttemplates', $catalog),
                    'charges' => $item('Cobros', 'icon-list', 'charges', $grooming),
                    'imports' => $item('Importar historias', 'icon-list', 'imports', $catalog),
                    'exports' => $item('Exportar datos', 'icon-list', 'exports', $catalog),
                    'auditlogs' => $item('Bitácora', 'icon-list', 'auditlogs', $owner),
                    'settings'  => $item('Configuración', 'icon-cog', 'settings', $owner),
                ],
            ],
        ];

        if ($animal) {
            $menu['medical']['sideMenu'] = array_slice($menu['medical']['sideMenu'], 0, 4, true)
                + ['groomings' => $item('Peluquería y spa', 'icon-tags', 'groomings', $grooming)]
                + array_slice($menu['medical']['sideMenu'], 4, null, true);
        }

        return $menu;
    }
}
