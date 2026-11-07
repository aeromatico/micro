<?php namespace Aero\Clinica\Models;

use Aero\Clinica\Classes\Audit;
use Aero\Clinica\Classes\TenantOwned;
use Model;

/** Inmutable: una vez escrita, la bitácora no se edita ni se borra. */
class AuditLog extends Model
{
    use TenantOwned;

    public $table = 'aero_clinica_audit_logs';

    public const UPDATED_AT = null;

    public $fillable = ['tenant_id', 'user_id', 'user_name', 'patient_id', 'action', 'subject_type', 'subject_id', 'fields', 'ip'];

    public $belongsTo = ['patient' => [Patient::class, 'key' => 'patient_id']];

    public function beforeUpdate(): void
    {
        throw new \ApplicationException('La bitácora no se puede modificar.');
    }

    public function beforeDelete(): void
    {
        throw new \ApplicationException('La bitácora no se puede eliminar.');
    }

    public function getActionLabelAttribute(): string
    {
        return Audit::actions()[$this->action] ?? (string) $this->action;
    }

    public function getFieldsListAttribute(): string
    {
        return implode(', ', (array) json_decode((string) $this->fields, true));
    }
}
