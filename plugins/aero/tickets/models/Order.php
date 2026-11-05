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

    public $fillable = ['event_id', 'guest_id', 'buyer_name', 'buyer_email', 'buyer_phone', 'buyer_document', 'buyer_extra'];

    public $jsonable = ['commission_terms'];
    public $dates = ['expires_at', 'verifying_at', 'confirmed_at', 'cancelled_at'];

    public $belongsTo = ['event' => [Event::class, 'key' => 'event_id'], 'guest' => [Guest::class, 'key' => 'guest_id']];
    public $hasMany = [
        'items' => [OrderItem::class, 'key' => 'order_id'],
        'tickets' => [Ticket::class, 'key' => 'order_id'],
    ];

    public static array $statuses = [
        'pending_payment' => 'Pendiente de pago', 'verifying' => 'En verificación', 'confirmed' => 'Confirmada',
        'cancelled' => 'Cancelada', 'expired' => 'Vencida', 'incident' => 'En incidencia',
    ];
    public static array $sources = ['public' => 'Compra pública', 'guest' => 'Invitación', 'manual' => 'Organizador'];

    /** Estados en los que la orden aún retiene cupo. */
    public const LIVE = ['pending_payment', 'verifying', 'incident'];

    public function getStatusOptions(): array { return self::$statuses; }
    public function getSourceOptions(): array { return self::$sources; }

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
    public function reopen(): void { OrderService::reopen($this); }
    public function markVerifying(?string $reference = null): void { OrderService::markVerifying($this, $reference); }
    public function cancel(?string $reason = null): void { OrderService::cancel($this, $reason); }
    public function markIncident(?string $note = null): void { OrderService::incident($this, $note); }
    public static function expireLapsed(): int { return OrderService::expireLapsed(); }
}
