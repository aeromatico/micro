<?php namespace Aero\Tickets\Models;

use Model;

/** Línea de una orden: tipo de entrada, cantidad y precios congelados al comprar. */
class OrderItem extends Model
{
    public $table = 'aero_tickets_order_items';
    public $fillable = ['tenant_id', 'order_id', 'ticket_type_id', 'name', 'kind', 'quantity', 'unit_price', 'unit_fee', 'subtotal', 'fees', 'total'];

    public $belongsTo = ['order' => [Order::class, 'key' => 'order_id']];

    public function scopeVisible($q)
    {
        if (\Aero\Tickets\Classes\CurrentTenant::isAdmin()) {
            return $q;
        }

        return $this->scopeForTenant($q, \Aero\Tickets\Classes\CurrentTenant::id());
    }

    public function scopeForTenant($q, ?int $tenantId)
    {
        return $tenantId ? $q->where($this->getTable() . '.tenant_id', $tenantId) : $q->whereRaw('1 = 0');
    }
}
