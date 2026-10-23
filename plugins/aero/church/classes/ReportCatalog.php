<?php namespace Aero\Church\Classes;

/** Qué reportes existen y quién puede pedir cada uno. El detalle con aportantes es solo de tesorería y administración. */
class ReportCatalog
{
    public const PEOPLE = ['aero.church.use', 'aero.church.pastor', 'aero.church.secretary', 'aero.church.superadmin'];
    public const SUMMARY_MONEY = ['aero.church.use', 'aero.church.treasury', 'aero.church.pastor', 'aero.church.superadmin'];
    public const DETAIL_MONEY = ['aero.church.use', 'aero.church.treasury', 'aero.church.superadmin'];

    public static function all(): array
    {
        return [
            'members'       => ['label' => 'Directorio de miembros', 'perms' => self::PEOPLE, 'filters' => ['status', 'branch']],
            'growth'        => ['label' => 'Crecimiento de la congregación', 'perms' => self::PEOPLE, 'filters' => []],
            'attendance'    => ['label' => 'Asistencia y comparación entre periodos', 'perms' => self::PEOPLE, 'filters' => ['period']],
            'groups'        => ['label' => 'Participación por ministerio y grupo', 'perms' => self::PEOPLE, 'filters' => ['period']],
            'visitors'      => ['label' => 'Seguimiento de visitantes', 'perms' => self::PEOPLE, 'filters' => ['period']],
            'giving'        => ['label' => 'Resumen de aportes (sin nombres)', 'perms' => self::SUMMARY_MONEY, 'filters' => ['period', 'branch']],
            'contributions' => ['label' => 'Aportes confirmados (con aportantes)', 'perms' => self::DETAIL_MONEY, 'filters' => ['period']],
        ];
    }

    public static function allowed($user): array
    {
        return array_filter(self::all(), fn ($r) => $user && $user->hasAnyAccess($r['perms']));
    }

    public static function run(string $key, ReportService $svc, array $f): array
    {
        return match ($key) {
            'members'       => $svc->members($f),
            'growth'        => $svc->growth(),
            'attendance'    => $svc->attendance($f['from'] ?? null, $f['to'] ?? null),
            'groups'        => $svc->groups($f['from'] ?? null, $f['to'] ?? null),
            'visitors'      => $svc->visitors($f['from'] ?? null, $f['to'] ?? null),
            'giving'        => $svc->givingSummary($f['from'] ?? null, $f['to'] ?? null, !empty($f['branch']) ? (int) $f['branch'] : null),
            'contributions' => $svc->contributions($f['from'] ?? null, $f['to'] ?? null),
            default         => throw new ChurchException('Reporte desconocido.'),
        };
    }
}
