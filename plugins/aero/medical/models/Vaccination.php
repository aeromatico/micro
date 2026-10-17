<?php namespace Aero\Medical\Models;

use Aero\Medical\Classes\Audit;
use Aero\Medical\Classes\TenantOwned;
use Model;

/** Dosis aplicada: vacuna, dosis, fecha, lote y próxima dosis (que puede disparar un recordatorio). */
class Vaccination extends Model
{
    use TenantOwned;

    public $table = 'aero_medical_vaccinations';

    public $fillable = ['tenant_id', 'patient_id', 'vaccine_id', 'vaccine_name', 'dose_label', 'applied_on', 'lot', 'next_due_on', 'applied_by', 'consultation_id', 'notes'];

    public $casts = ['notes' => 'encrypted', 'applied_on' => 'date', 'next_due_on' => 'date'];

    protected $dates = ['reminder_sent_at'];

    public $belongsTo = [
        'patient' => [Patient::class, 'key' => 'patient_id'],
        'vaccine' => [Vaccine::class, 'key' => 'vaccine_id'],
    ];

    public function isOverdue(): bool
    {
        return $this->next_due_on && $this->next_due_on->lt(now()->startOfDay());
    }

    public function afterCreate(): void
    {
        Audit::record('create', $this->tenant_id, $this->patient_id, 'vaccination', $this->id);
    }

    public function afterUpdate(): void
    {
        Audit::record('update', $this->tenant_id, $this->patient_id, 'vaccination', $this->id, array_keys($this->getChanges()));
    }

    public function afterDelete(): void
    {
        Audit::record('delete', $this->tenant_id, $this->patient_id, 'vaccination', $this->id);
    }
}
