<?php namespace Aero\Tickets\Models;

use Aero\Tickets\Classes\PaymentService;
use Model;

/**
 * Intento/registro de cobro de una orden. Solo PaymentService lo escribe; nunca se borra.
 * Un comprobante o una referencia informados por el comprador NO aprueban nada: solo un administrador
 * autorizado o el proveedor de pago (confirmación automática) pueden aprobarlo.
 */
class Payment extends Model
{
    use \Aero\Tickets\Classes\TenantScoped;

    public $table = 'aero_tickets_payments';
    public $guarded = [];
    public $dates = ['submitted_at', 'verified_at', 'refunded_at'];

    public $belongsTo = [
        'order' => [Order::class, 'key' => 'order_id'],
        'event' => [Event::class, 'key' => 'event_id'],
    ];
    public $hasMany = [
        'proofs' => [PaymentProof::class, 'key' => 'payment_id'],
        'history' => [PaymentEvent::class, 'key' => 'payment_id', 'order' => 'id asc'],
    ];

    public static array $methods = ['qr' => 'QR bancario', 'transfer' => 'Transferencia bancaria', 'manual' => 'Pago manual autorizado', 'card' => 'Tarjeta'];
    public static array $statuses = [
        'pending' => 'Pendiente', 'submitted' => 'Pendiente de verificación', 'approved' => 'Aprobado', 'rejected' => 'Rechazado',
        'cancelled' => 'Cancelado', 'expired' => 'Vencido', 'refund_pending' => 'Reembolso pendiente', 'refunded' => 'Reembolsado',
    ];

    /** Estados en los que el pago aún puede resolverse. */
    public const OPEN = ['pending', 'submitted'];

    public function getMethodOptions(): array { return self::$methods; }
    public function getStatusOptions(): array { return self::$statuses; }
    public function getStatusLabelAttribute(): string { return self::$statuses[$this->status] ?? $this->status; }
    public function getMethodLabelAttribute(): string { return self::$methods[$this->method] ?? $this->method; }

    public function beforeSave(): void
    {
        if (!PaymentService::$writing) {
            throw new \ApplicationException('Los pagos solo se modifican desde el módulo de pagos.');
        }
    }

    public function beforeDelete(): void
    {
        throw new \ApplicationException('Los pagos se conservan como historial financiero.');
    }
}
