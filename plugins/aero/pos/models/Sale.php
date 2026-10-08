<?php namespace Aero\Pos\Models;

use Model;

class Sale extends Model
{
    public $table = 'aero_pos_sales';

    public $fillable = [
        'tenant_id', 'order_id', 'shift_id', 'terminal_id', 'cashier_user_id', 'table_id', 'tab_state',
        'tip_total', 'discount_reason', 'nit', 'tax_name', 'client_uuid', 'closed_at',
    ];

    protected $dates = ['closed_at'];

    public $belongsTo = [
        'order'    => [\Aero\Shop\Models\Order::class],
        'shift'    => [Shift::class],
        'terminal' => [Terminal::class],
        'cashier'  => [\Backend\Models\User::class, 'key' => 'cashier_user_id'],
        'pos_table' => [PosTable::class, 'key' => 'table_id'],
    ];

    public $hasMany = [
        'payments' => [Payment::class],
    ];

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /** Importe a cobrar: total de la orden + propina. */
    public function amountDue(): float
    {
        return round((float) $this->order->grand_total + (float) $this->tip_total, 4);
    }

    public function amountPaid(): float
    {
        return round((float) $this->payments()->where('status', 'completed')->sum('amount'), 4);
    }
}
