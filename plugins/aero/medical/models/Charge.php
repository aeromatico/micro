<?php namespace Aero\Medical\Models;

use Aero\Medical\Classes\Audit;
use Aero\Medical\Classes\TenantOwned;
use Model;

/** Cobro de una atención. Solo concepto, monto y estado; sin contenido clínico. */
class Charge extends Model
{
    use TenantOwned;

    public $table = 'aero_medical_charges';

    public $fillable = ['tenant_id', 'patient_id', 'consultation_id', 'booking_id', 'concept', 'amount', 'currency', 'method', 'qr_code_id', 'bank_account_id', 'registered_by'];

    public $casts = ['concept' => 'encrypted', 'amount' => 'decimal:2'];

    protected $dates = ['paid_at'];

    public $belongsTo = ['patient' => [Patient::class, 'key' => 'patient_id']];

    public const METHODS = ['cash' => 'Efectivo', 'transfer' => 'Transferencia', 'qr' => 'QR (Bolivia Pay)', 'other' => 'Otro'];
    public const STATUSES = ['pending' => 'Pendiente', 'paid' => 'Cobrado', 'cancelled' => 'Anulado'];

    public function getMethodLabelAttribute(): string
    {
        return self::METHODS[$this->method] ?? (string) $this->method;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? (string) $this->status;
    }

    public function afterCreate(): void
    {
        Audit::record('create', $this->tenant_id, $this->patient_id, 'charge', $this->id);
    }

    public function afterUpdate(): void
    {
        Audit::record('update', $this->tenant_id, $this->patient_id, 'charge', $this->id, array_keys($this->getChanges()));
    }
}
