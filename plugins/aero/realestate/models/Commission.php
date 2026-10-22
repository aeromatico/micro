<?php namespace Aero\Realestate\Models;

use Model;

/** Comisión de una operación cerrada (venta, alquiler o anticrético). La genera DealService con la regla vigente. */
class Commission extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \Aero\Realestate\Classes\TenantOwned;

    public $table = 'aero_realestate_commissions';

    public $fillable = ['tenant_id', 'status', 'paid_on', 'notes'];

    public $rules = ['status' => 'required|in:pending,approved,paid,cancelled'];

    public $dates = ['paid_on'];

    public $belongsTo = [
        'agent'    => [Agent::class, 'key' => 'agent_id'],
        'property' => [Property::class, 'key' => 'property_id'],
        'client'   => [Client::class, 'key' => 'client_id'],
        'lead'     => [Lead::class, 'key' => 'lead_id'],
        'offer'    => [Offer::class, 'key' => 'offer_id'],
    ];

    public static array $statuses = ['pending' => 'Pendiente', 'approved' => 'Aprobada', 'paid' => 'Pagada', 'cancelled' => 'Anulada'];

    public function getStatusOptions(): array
    {
        return self::$statuses;
    }

    public function getModalityNameAttribute(): string
    {
        return Offer::$modalities[$this->modality] ?? (string) $this->modality;
    }

    public function beforeSave(): void
    {
        if ($this->status === 'paid' && !$this->paid_on) {
            $this->paid_on = now();
        }
        if ($this->status !== 'paid') {
            $this->paid_on = null;
        }
    }
}
