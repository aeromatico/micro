<?php namespace Aero\Tickets\Models;

use ApplicationException;
use Aero\Tickets\Classes\OrderService;
use Model;

/**
 * Orden de compra: agrupa las entradas de una compra, congela precios y datos del comprador
 * y lleva el estado del pago. Las órdenes nunca se borran (historial).
 */
class Order extends Model
{
    use \Aero\Tickets\Classes\BelongsToEvent;

    public $table = 'aero_tickets_orders';

    public $fillable = ['event_id', 'buyer_name', 'buyer_email', 'buyer_phone', 'buyer_document', 'buyer_extra'];

    public $jsonable = ['commission_terms'];
    public $dates = ['expires_at', 'verifying_at', 'confirmed_at', 'cancelled_at'];

    public $belongsTo = ['event' => [Event::class, 'key' => 'event_id']];
    public $hasMany = [
        'items' => [OrderItem::class, 'key' => 'order_id'],
        'tickets' => [Ticket::class, 'key' => 'order_id'],
    ];

    public static array $statuses = [
        'pending_payment' => 'Pendiente de pago', 'verifying' => 'En verificación', 'confirmed' => 'Confirmada',
        'cancelled' => 'Cancelada', 'expired' => 'Vencida', 'incident' => 'En incidencia',
    ];
    public static array $sources = ['public' => 'Compra pública', 'manual' => 'Registrada por el organizador'];

    /** Estados en los que la orden aún retiene cupo. */
    public const LIVE = ['pending_payment', 'verifying', 'incident'];

    public function getStatusOptions(): array { return self::$statuses; }
    public function getSourceOptions(): array { return self::$sources; }

    /** Qué debe pasar ahora con esta orden (texto de ayuda del panel). */
    public function getNextStepAttribute(): string
    {
        $free = (float) $this->total <= 0;

        return match ($this->status) {
            'pending_payment' => 'Esperando que el comprador pague. Todavía no tiene entradas ni ocupa lugares. Si ya te pagó por fuera (efectivo, depósito), pulsa «Confirmar cobro» y se emiten sus entradas.',
            'verifying' => $free
                ? 'Solicitud de inscripción: apruébala para que el comprador reciba sus entradas.'
                : 'El comprador avisó su pago. Revisa tu banco: si el dinero llegó, pulsa «Confirmar cobro» (se emiten las entradas); si no cuadra, rechaza ese pago desde «Pagos».',
            'incident' => 'Algo no cuadra con el pago (mira «Pagos»). Si ya se resolvió, «Confirmar cobro»; si no, «Cancelar orden».',
            'confirmed' => 'Compra completada: las entradas ya están emitidas y enviadas al comprador.',
            'cancelled' => 'Orden cancelada. Si había un pago recibido, está en «Pagos» como reembolso pendiente.',
            'expired' => 'Venció sin pago y se dio por abandonada. No retiene lugares. Si el comprador paga tarde, el pago se acepta si aún hay lugares.',
            default => '',
        };
    }

    public function getStatusLabelAttribute(): string { return self::$statuses[$this->status] ?? $this->status; }

    public function isLapsed(): bool
    {
        return $this->status === 'pending_payment' && $this->expires_at && $this->expires_at->isPast();
    }

    public function beforeDelete(): void
    {
        throw new ApplicationException('Las órdenes se conservan como historial; cancélala en su lugar.');
    }

    /** Las órdenes solo se escriben desde OrderService. */
    public function beforeSave(): void
    {
        if (!OrderService::$writing) {
            throw new ApplicationException('Las órdenes solo se modifican desde el servicio de compra.');
        }
    }

    // Acciones (delegan en el servicio, que bloquea y registra eventos)
    public function confirm(): void { OrderService::confirm($this); }
    public function markVerifying(?string $reference = null): void { OrderService::markVerifying($this, $reference); }
    public function cancel(?string $reason = null): void { OrderService::cancel($this, $reason); }
    public function markIncident(?string $note = null): void { OrderService::incident($this, $note); }
    public static function expireLapsed(): int { return OrderService::expireLapsed(); }
}
