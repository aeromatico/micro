<?php namespace Aero\Medical\Classes;

use Aero\Medical\Models\AuditLog;
use BackendAuth;

/**
 * Bitácora de quién abrió, editó, firmó o imprimió qué. Guarda NOMBRES de
 * campos, nunca valores clínicos, y nunca rompe la acción que registra.
 */
class Audit
{
    public static function record(string $action, ?int $tenantId, ?int $patientId = null, ?string $subjectType = null, ?int $subjectId = null, array $fields = []): void
    {
        try {
            $user = BackendAuth::getUser();
            $fields = array_values(array_diff($fields, ['updated_at', 'created_at', 'deleted_at']));

            AuditLog::create([
                'tenant_id'    => $tenantId,
                'user_id'      => $user?->id,
                'user_name'    => $user ? trim("{$user->first_name} {$user->last_name}") ?: $user->email : null,
                'patient_id'   => $patientId,
                'action'       => $action,
                'subject_type' => $subjectType,
                'subject_id'   => $subjectId,
                'fields'       => $fields ? json_encode($fields) : null,
                'ip'           => app()->runningInConsole() ? null : request()->ip(),
            ]);
        } catch (\Throwable $e) {
            \Log::warning('aero.medical: no se pudo escribir la bitácora (' . get_class($e) . ')');
        }
    }

    public static function actions(): array
    {
        return [
            'view' => 'Abrió', 'create' => 'Creó', 'update' => 'Editó', 'delete' => 'Eliminó', 'restore' => 'Restauró',
            'settings' => 'Cambió la configuración', 'sign' => 'Firmó', 'print' => 'Imprimió', 'export' => 'Exportó', 'addendum' => 'Agregó una adenda', 'import' => 'Importó archivos', 'download' => 'Descargó un archivo',
        ];
    }
}
