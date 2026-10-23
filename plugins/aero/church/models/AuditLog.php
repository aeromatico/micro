<?php namespace Aero\Church\Models;

use Model;

/**
 * Historial de operaciones administrativas. Solo se escribe desde Audit::record(); no se edita ni se borra
 * desde el panel. Guarda NOMBRES de campos cambiados, nunca valores (ni montos, ni datos personales).
 */
class AuditLog extends Model
{
    use \Aero\Church\Classes\TenantOwned;

    public const ACTIONS = [
        'created' => 'Alta', 'updated' => 'Cambio', 'deleted' => 'Eliminación', 'viewed' => 'Consulta',
        'exported' => 'Exportación', 'sent' => 'Envío', 'confirmed' => 'Confirmación', 'cancelled' => 'Anulación',
    ];

    public $table = 'aero_church_audit_logs';

    public $timestamps = false;

    public $fillable = ['tenant_id', 'user_id', 'user_label', 'action', 'subject_type', 'subject_id', 'summary', 'changes', 'ip', 'created_at'];

    public $jsonable = ['changes'];

    public $dates = ['created_at'];

    /** Es un registro de solo anexado: no se modifica ni se elimina. */
    public function beforeUpdate(): void
    {
        throw new \ApplicationException('El registro de auditoría no se modifica.');
    }

    public function beforeDelete(): void
    {
        throw new \ApplicationException('El registro de auditoría no se elimina.');
    }
}
