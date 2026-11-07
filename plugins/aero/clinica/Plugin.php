<?php namespace Aero\Clinica;

use Backend;
use System\Classes\PluginBase;

/**
 * Historia clínica electrónica por negocio (tenant), de medicina humana,
 * para consultorios, centros de salud y clínicas. Acoplamiento blando con Aero.Office (clientes, profesionales,
 * reservas) por class_exists() y eventos aero.*. Datos de salud: cifrados,
 * auditados y nunca en eventos hacia webhooks.
 */
class Plugin extends PluginBase
{
    public function pluginDetails(): array
    {
        return [
            'name'        => 'Clínica',
            'description' => 'Historia clínica electrónica para consultorios, centros de salud y clínicas: pacientes, consultas, recetas y seguimiento.',
            'author'      => 'Aero',
            'icon'        => 'icon-heartbeat',
        ];
    }

    public function register(): void
    {
        $this->registerConsoleCommand('clinica:import-cie10', \Aero\Clinica\Console\ImportCie10::class);
        $this->registerConsoleCommand('clinica:remind', \Aero\Clinica\Console\RemindCommand::class);
        $this->registerConsoleCommand('clinica:seed-demo', \Aero\Clinica\Console\SeedDemo::class);
    }

    public function boot(): void
    {
        // Aero.Sheets (si está) puede ofrecer los pacientes como fuente de importación; solo anuncia, no depende.
        \Event::listen('aero.sheets.registerModels', fn () => [['model' => \Aero\Clinica\Models\Patient::class, 'label' => 'Pacientes del consultorio']]);

        // API de pacientes (solo datos administrativos): únicamente si Aero.Api está instalado.
        if (class_exists(\Aero\Api\Classes\EndpointRegistry::class)) {
            $this->app['router']->group([], function () {
                require __DIR__ . '/routes.php';
            });

            \Event::listen('aero.api.registerScopes', fn () => ['clinica' => ['label' => 'Clínica', 'scopes' => \Aero\Clinica\Classes\Api\Scopes::all()]]);

            \Event::listen('aero.api.registerEndpoints', fn () => ['clinica' => [
                'label' => 'Clínica',
                'endpoints' => [
                    [
                        'method' => 'GET', 'path' => '/api/v1/clinica/patients', 'scope' => \Aero\Clinica\Classes\Api\Scopes::PATIENTS_READ,
                        'summary' => 'Lista de pacientes (solo datos administrativos; nunca contenido clínico). Requiere la key del negocio.',
                        'query' => [
                            ['name' => 'q', 'type' => 'string', 'help' => 'Nombre o documento.'],
                            ['name' => 'active', 'type' => 'boolean'],
                            ['name' => 'per_page', 'type' => 'integer', 'default' => 50, 'help' => 'Máx. 200.'],
                        ],
                    ],
                    [
                        'method' => 'GET', 'path' => '/api/v1/clinica/patients/{id}', 'scope' => \Aero\Clinica\Classes\Api\Scopes::PATIENTS_READ,
                        'summary' => 'Detalle administrativo de un paciente.',
                        'path_params' => [['name' => 'id', 'type' => 'integer', 'required' => true, 'help' => 'Id del paciente.']],
                    ],
                ],
            ]]);
        }

        // Conciliación de cobros por QR: Aero.Pay avisa el cambio de estado (solo actúa sobre sus propios QR).
        \Event::listen('aero.pay.qrStatusChanged', [\Aero\Clinica\Classes\ChargeService::class, 'onQrStatus']);

        // Botón en la reserva de Office (punto de extensión mínimo en su vista): solo personal clínico.
        \Event::listen('aero.office.bookingDetailActions', function ($booking) {
            $user = \BackendAuth::getUser();
            if (!$user || !$user->hasAccess(['aero.clinica.professional', 'aero.clinica.superadmin'], false)
                || !in_array($booking->status, ['confirmed', 'in_service', 'completed'], true)) {
                return null;
            }

            $note = \Aero\Clinica\Models\Consultation::where('tenant_id', $booking->tenant_id)->where('booking_id', $booking->id)->first();

            return $note
                ? '<a class="btn btn-primary" href="' . e(Backend::url('aero/clinica/consultations/update/' . $note->id)) . '">Abrir la nota clínica</a>'
                : '<a class="btn btn-primary" href="' . e(Backend::url('aero/clinica/today/booking/' . $booking->id)) . '">Iniciar consulta</a>';
        });
    }

    public function registerSchedule($schedule): void
    {
        $schedule->command('clinica:remind')->dailyAt('09:00');
    }

    public function registerPermissions(): array
    {
        return [
            'aero.clinica.use' => [
                'tab'   => 'Clínica',
                'label' => 'Administrar el módulo: pacientes (datos administrativos) y bitácora',
            ],
            'aero.clinica.settings' => [
                'tab'   => 'Clínica',
                'label' => 'Configuración del módulo (plantillas, recordatorios) y bitácora (propietario)',
            ],
            'aero.clinica.professional' => [
                'tab'   => 'Clínica',
                'label' => 'Profesional: ver el contenido clínico, escribir y firmar notas',
            ],
            'aero.clinica.reception' => [
                'tab'   => 'Clínica',
                'label' => 'Recepción: datos administrativos de pacientes, sin contenido clínico',
            ],
            'aero.clinica.superadmin' => [
                'tab'   => 'Clínica',
                'label' => 'Administrar Clínica: datos de todos los negocios',
            ],
        ];
    }

    public function registerNavigation(): array
    {
        $all = ['aero.clinica.use', 'aero.clinica.reception', 'aero.clinica.professional', 'aero.clinica.superadmin'];
        $clinical = ['aero.clinica.professional', 'aero.clinica.superadmin'];
        $catalog = ['aero.clinica.professional', 'aero.clinica.settings', 'aero.clinica.superadmin'];
        $owner = ['aero.clinica.settings', 'aero.clinica.superadmin'];
        $billing = ['aero.clinica.professional', 'aero.clinica.reception', 'aero.clinica.use', 'aero.clinica.settings', 'aero.clinica.superadmin'];
        $item = fn (string $label, string $icon, string $path, array $p) => [
            'label' => $label, 'icon' => $icon, 'url' => Backend::url('aero/clinica/' . $path), 'permissions' => $p,
        ];

        $menu = [
            'clinica' => [
                'label'       => 'Clínica',
                'url'         => Backend::url('aero/clinica/patients'),
                'icon'        => 'icon-heartbeat',
                'iconSvg'     => null,
                'permissions' => $all,
                'order'       => 526,
                'sideMenu'    => [
                    'start'     => $item('Primeros pasos', 'icon-list', 'start', $all),
                    'today'     => $item('Agenda de hoy', 'icon-calendar', 'today', $clinical),
                    'patients'  => $item('Pacientes', 'icon-users', 'patients', $all),
                    'consultations' => $item('Consultas', 'icon-heartbeat', 'consultations', $clinical),
                    'signature' => $item('Mi firma', 'icon-pencil', 'signature', $clinical),
                    'notetemplates' => $item('Plantillas de nota', 'icon-file-text', 'notetemplates', $owner),
                    'diagnosiscatalogs' => $item('Diagnósticos del negocio', 'icon-tags', 'diagnosiscatalogs', $catalog),
                    'examcatalogs' => $item('Exámenes', 'icon-tags', 'examcatalogs', $catalog),
                    'analytes' => $item('Analitos', 'icon-tags', 'analytes', $catalog),
                    'ranges' => $item('Rangos alto/bajo', 'icon-bar-chart', 'ranges', $catalog),
                    'documenttemplates' => $item('Formatos de documentos', 'icon-file-text', 'documenttemplates', $catalog),
                    'charges' => $item('Cobros', 'icon-list', 'charges', $billing),
                    'imports' => $item('Importar historias', 'icon-list', 'imports', $catalog),
                    'exports' => $item('Exportar datos', 'icon-list', 'exports', $catalog),
                    'auditlogs' => $item('Bitácora', 'icon-list', 'auditlogs', $owner),
                    'settings'  => $item('Configuración', 'icon-cog', 'settings', $owner),
                ],
            ],
        ];

        return $menu;
    }
}
