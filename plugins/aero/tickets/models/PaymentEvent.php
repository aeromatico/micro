<?php namespace Aero\Tickets\Models;

use Model;

/**
 * Historial financiero: solo se agrega. No se edita ni se borra (ni siquiera al borrar el pago):
 * es la traza de quién hizo qué con cada cobro.
 */
class PaymentEvent extends Model
{
    use \Aero\Tickets\Classes\TenantScoped;

    public $table = 'aero_tickets_payment_events';
    public $timestamps = false;
    public $guarded = [];
    public $jsonable = ['meta'];
    public $dates = ['created_at'];

    public static array $actions = [
        'created' => 'Intento de pago creado', 'submitted' => 'Comprador informó el pago', 'proof_uploaded' => 'Comprobante adjuntado',
        'approved' => 'Pago aprobado', 'rejected' => 'Pago rechazado', 'auto_confirmed' => 'Confirmado por el proveedor',
        'expired' => 'Pago vencido', 'late' => 'Pago tardío', 'flagged' => 'Marcado como sospechoso',
        'refund_pending' => 'Reembolso pendiente', 'refunded' => 'Reembolso registrado', 'cancelled' => 'Intento cancelado',
        'manual_registered' => 'Pago manual registrado', 'order_reopened' => 'Orden reabierta',
    ];

    public function getActionLabelAttribute(): string
    {
        return self::$actions[$this->action] ?? $this->action;
    }

    public function beforeCreate(): void
    {
        $this->created_at = $this->created_at ?: now();
    }

    public function beforeUpdate(): void
    {
        throw new \ApplicationException('El historial financiero no se modifica.');
    }

    public function beforeDelete(): void
    {
        throw new \ApplicationException('El historial financiero no se borra.');
    }
}
