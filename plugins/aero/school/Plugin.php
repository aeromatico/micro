<?php namespace Aero\School;

use Backend;
use Event;
use System\Classes\PluginBase;

/**
 * Colegios: estudiantes, cursos y apoderados (sobre RainLab.User), cobro de
 * matrícula y pensiones con recordatorios por WhatsApp, estado de cuenta,
 * asistencia con aviso, calificaciones y boletín en PDF (Bolivia), comunicados
 * y reportes de morosidad. Integraciones blandas con Pay, Hello y Sites.
 */
class Plugin extends PluginBase
{
    public $require = ['RainLab.User'];

    public function pluginDetails(): array
    {
        return [
            'name'        => 'Colegios',
            'description' => 'Estudiantes, apoderados, pensiones con cobranza por WhatsApp, asistencia, boletines y comunicados.',
            'author'      => 'Aero',
            'icon'        => 'icon-university',
        ];
    }

    public function register(): void
    {
        $this->registerConsoleCommand('school:daily', \Aero\School\Console\DailyCommand::class);
    }

    public function registerComponents(): array
    {
        return [
            \Aero\School\Components\SchoolPortal::class => 'schoolPortal',
        ];
    }

    /** Para los temas: {% if school_enabled() %} (el dominio tiene colegio y está activo). */
    public function registerMarkupTags(): array
    {
        return ['functions' => ['school_enabled' => function () {
            $tenant = \Aero\Sites\Models\Tenant::resolveFromDomain(request()->getHost());

            return $tenant && \Aero\School\Models\Settings::isEnabled($tenant->id)
                && \Aero\School\Models\Student::where('tenant_id', $tenant->id)->exists();
        }]];
    }

    public function boot(): void
    {
        // Pago del QR de una cuota → registro inmediato (el schedule es el respaldo).
        if (class_exists(\Aero\Pay\Models\QrCode::class)) {
            \Aero\Pay\Models\QrCode::extend(function ($model) {
                $model->bindEvent('model.afterUpdate', function () use ($model) {
                    if ($model->wasChanged('status') && $model->status === 'paid'
                        && str_starts_with((string) $model->external_reference, 'school-cuota-')) {
                        app(\Aero\School\Classes\Billing::class)->syncQrPayments();
                    }
                });
            });
        }

        // Un alumno nuevo recibe de inmediato las cuotas de los conceptos de su curso.
        \Aero\School\Models\Student::extend(function ($model) {
            $model->bindEvent('model.afterCreate', function () use ($model) {
                if ($model->course_id && ($model->status ?: 'active') === 'active') {
                    try {
                        app(\Aero\School\Classes\Billing::class)->generateForStudent($model);
                    } catch (\Throwable $e) {
                        \Log::warning('[school] No se generaron cuotas del alumno ' . $model->id . ': ' . $e->getMessage());
                    }
                }
            });
        });
    }

    public function registerSchedule($schedule): void
    {
        $schedule->command('school:daily')->dailyAt('09:00');
        $schedule->call(fn () => app(\Aero\School\Classes\Billing::class)->syncQrPayments())->everyFiveMinutes();
    }

    public function registerPermissions(): array
    {
        return [
            'aero.school.use' => [
                'tab'   => 'Colegios',
                'label' => 'Usar el colegio: estudiantes, cobranza, asistencia, calificaciones, comunicados y reportes propios',
            ],
            'aero.school.superadmin' => [
                'tab'   => 'Colegios',
                'label' => 'Administrar Colegios: datos de todos los tenants',
            ],
        ];
    }

    public function registerNavigation(): array
    {
        $p = ['aero.school.use', 'aero.school.superadmin'];
        $item = fn (string $label, string $icon, string $path) => ['label' => $label, 'icon' => $icon, 'url' => Backend::url('aero/school/' . $path), 'permissions' => $p];

        return [
            'school' => [
                'label'       => 'Colegio',
                'url'         => Backend::url('aero/school/dashboard'),
                'icon'        => 'icon-university',
                'iconSvg'     => null,
                'permissions' => $p,
                'order'       => 524,
                'sideMenu'    => [
                    'dashboard'     => $item('Panel', 'icon-th-large', 'dashboard'),
                    'students'      => $item('Estudiantes', 'icon-users', 'students'),
                    'guardians'     => $item('Apoderados', 'icon-user', 'guardians'),
                    'courses'       => $item('Cursos', 'icon-list', 'courses'),
                    'attendance'    => $item('Asistencia', 'icon-calendar', 'attendance'),
                    'gradebook'     => $item('Calificaciones (próx.)', 'icon-book', 'gradebook'),
                    'collect'       => $item('Cobrar', 'icon-money', 'collect'),
                    'charges'       => $item('Cuotas', 'icon-list-alt', 'charges'),
                    'payments'      => $item('Pagos y recibos', 'icon-file-text', 'payments'),
                    'prices'        => $item('Pensiones y matrícula', 'icon-tags', 'prices'),
                    'feeplans'      => $item('Otros cobros', 'icon-list-alt', 'feeplans'),
                    'announcements' => $item('Comunicados', 'icon-bullhorn', 'announcements'),
                    'reports'       => $item('Reportes', 'icon-bar-chart', 'reports'),
                    'years'         => $item('Gestiones', 'icon-history', 'years'),
                    'settings'      => $item('Configuración', 'icon-cog', 'settings'),
                ],
            ],
        ];
    }
}
