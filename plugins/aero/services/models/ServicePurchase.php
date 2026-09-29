<?php namespace Aero\Services\Models;

use Model;

/**
 * Una compra de un plan de servicio por un tenant. `plan_snapshot` congela el
 * plan tal como estaba al pagar (nombre, precio, moneda): si el catálogo
 * cambia después, esta compra sigue mostrando lo que el cliente pagó. El
 * cobro real vive en Aero.Credits (`credit_transaction_id`); este modelo solo
 * lleva el estado de cumplimiento (pendiente / entregado / cancelado).
 */
class ServicePurchase extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_services_purchases';

    public const PENDING = 'pending';
    public const FULFILLED = 'fulfilled';
    public const CANCELLED = 'cancelled';

    public $fillable = [
        'tenant_id', 'service_id', 'plan_index', 'plan_snapshot', 'payment_method',
        'credit_type_code', 'amount', 'credit_transaction_id', 'status',
        'requested_by', 'fulfilled_by', 'fulfilled_at', 'cancelled_at', 'notes',
    ];

    public $rules = [
        'service_id'      => 'required',
        'tenant_id'       => 'required',
        'payment_method'  => 'required|in:credits,money',
    ];

    public $jsonable = ['plan_snapshot'];

    protected $dates = ['fulfilled_at', 'cancelled_at'];

    public $belongsTo = [
        'service' => [Service::class],
    ];

    public function scopePending($query)
    {
        return $query->where('status', self::PENDING);
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function getPlanNameAttribute(): string
    {
        return (string) (((array) $this->plan_snapshot)['name'] ?? '—');
    }

    public function getTenantNameAttribute(): string
    {
        if (!class_exists(\Aero\Sites\Models\Tenant::class)) {
            return (string) $this->tenant_id;
        }

        return \Aero\Sites\Models\Tenant::find($this->tenant_id)?->name ?? ('#' . $this->tenant_id);
    }

    /** "5 monedas de bronce" o "Bs 120,00", según el método de cobro. */
    public function getAmountLabelAttribute(): string
    {
        if ($this->payment_method === 'money') {
            return class_exists(\Aero\Credits\Classes\Money::class)
                ? \Aero\Credits\Classes\Money::label((int) $this->amount)
                : 'Bs ' . number_format((float) $this->amount / 10000, 2);
        }

        $type = class_exists(\Aero\Credits\Models\CreditType::class)
            ? \Aero\Credits\Models\CreditType::findByCode((string) $this->credit_type_code)
            : null;

        return number_format((int) $this->amount) . ' ' . ($type->label ?? $this->credit_type_code);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::PENDING   => 'Pendiente',
            self::FULFILLED => 'Entregado',
            self::CANCELLED => 'Cancelado',
            default         => (string) $this->status,
        };
    }
}
