<?php namespace Aero\Church\Classes;

use Aero\Church\Models\AuditLog;

/** Escribe en el historial de auditoría. NUNCA lanza: un fallo de auditoría no puede romper la operación. */
class Audit
{
    /** Los sembradores de demostración lo activan para no llenar el historial con altas masivas. */
    public static bool $muted = false;

    /** Campos que no se anotan ni como nombre (ruido). */
    protected const IGNORE = ['updated_at', 'created_at', 'deleted_at', 'tenant_id'];

    public static function record(string $action, ?string $type, ?int $id, string $summary, ?int $tenantId = null, array $fields = []): void
    {
        if (self::$muted) {
            return;
        }

        try {
            $user = \BackendAuth::getUser();
            $label = $user ? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: $user->login : null;

            AuditLog::create([
                'tenant_id'    => $tenantId ?? CurrentTenant::id(),
                'user_id'      => $user?->id,
                'user_label'   => $label ? mb_substr($label, 0, 120) : null,
                'action'       => $action,
                'subject_type' => $type,
                'subject_id'   => $id,
                'summary'      => mb_substr($summary, 0, 300),
                'changes'      => $fields ? array_values(array_diff($fields, self::IGNORE)) : null,
                'ip'           => app()->runningInConsole() ? null : request()->ip(),
                'created_at'   => now(),
            ]);
        } catch (\Throwable $e) {
            \Log::warning('[church] auditoría no registrada: ' . $e->getMessage());
        }
    }

    /** Para los modelos con `$audited`: alta, cambio y baja con el nombre legible del tipo. */
    public static function model(string $event, $model): void
    {
        $type = property_exists($model, 'auditType') ? $model::$auditType : strtolower(class_basename($model));
        $label = property_exists($model, 'auditLabel') ? $model::$auditLabel : $type;
        $fields = $event === 'updated' ? array_keys($model->getChanges()) : [];

        // Cambios que ya se anotan con una entrada propia (confirmar, anular, enviar): no se duplican.
        $quiet = property_exists($model, 'auditQuiet') ? $model::$auditQuiet : [];
        if ($event === 'updated' && !array_diff($fields, self::IGNORE, $quiet)) {
            return;
        }

        $verb = ['created' => 'creado', 'updated' => 'modificado', 'deleted' => 'eliminado'][$event] ?? $event;
        self::record($event, $type, (int) $model->getKey(), ucfirst($label) . " #{$model->getKey()} {$verb}", $model->tenant_id ? (int) $model->tenant_id : null, $fields);
    }
}
