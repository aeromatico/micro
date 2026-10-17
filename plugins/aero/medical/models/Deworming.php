<?php namespace Aero\Medical\Models;

use Aero\Medical\Classes\Audit;
use Aero\Medical\Classes\TenantOwned;
use Model;

/** Desparasitación aplicada (solo animales): producto, dosis, fecha, próxima y aviso. */
class Deworming extends Model
{
    use TenantOwned;

    public $table = 'aero_medical_dewormings';

    public $fillable = ['tenant_id', 'patient_id', 'product', 'kind', 'dose', 'applied_on', 'next_due_on', 'lot', 'applied_by', 'consultation_id', 'notes'];

    public $casts = ['notes' => 'encrypted', 'applied_on' => 'date', 'next_due_on' => 'date'];

    protected $dates = ['reminder_sent_at'];

    public $belongsTo = ['patient' => [Patient::class, 'key' => 'patient_id']];

    public const KINDS = ['internal' => 'Interna', 'external' => 'Externa', 'both' => 'Interna y externa'];

    public function getKindLabelAttribute(): string
    {
        return self::KINDS[$this->kind] ?? '';
    }

    public function isOverdue(): bool
    {
        return $this->next_due_on && $this->next_due_on->lt(now()->startOfDay());
    }

    public function afterCreate(): void
    {
        Audit::record('create', $this->tenant_id, $this->patient_id, 'deworming', $this->id);
    }

    public function afterUpdate(): void
    {
        Audit::record('update', $this->tenant_id, $this->patient_id, 'deworming', $this->id, array_keys($this->getChanges()));
    }

    public function afterDelete(): void
    {
        Audit::record('delete', $this->tenant_id, $this->patient_id, 'deworming', $this->id);
    }
}
