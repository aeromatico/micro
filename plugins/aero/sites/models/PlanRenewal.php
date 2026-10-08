<?php namespace Aero\Sites\Models;

use Model;

/**
 * Un ciclo de cobro de renovación (mensual/anual) para un tenant.
 * Generado por Console\GenerateRenewals, pagado vía el puente de
 * Plugin::bootPayRenewalBridge(), y vencido/suspendido por
 * Console\ExpireRenewals tras 5 días de gracia. Es un registro del
 * sistema: no tiene formulario de edición en el backend.
 */
class PlanRenewal extends Model
{
    public $table = 'aero_sites_plan_renewals';

    public $fillable = [
        'tenant_id', 'plan_id', 'period', 'cycle_due_at', 'status',
        'qr_code_id', 'payment_reference', 'amount', 'currency', 'paid_at',
    ];

    protected $dates = ['cycle_due_at', 'paid_at'];

    public $belongsTo = [
        'tenant' => [Tenant::class],
        'plan'   => [Plan::class],
    ];

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
