<?php namespace Aero\Tickets\Models;

use Model;

/** Comisión devengada por una orden cobrada. Base de las liquidaciones: settlement_id vacío = pendiente de liquidar. */
class CommissionEntry extends Model
{
    public $table = 'aero_tickets_commission_entries';
    public $guarded = [];
    public $jsonable = ['terms'];
    public $dates = ['accrued_at', 'voided_at'];

    public $belongsTo = ['event' => [Event::class, 'key' => 'event_id'], 'order' => [Order::class, 'key' => 'order_id']];

    public static array $statuses = ['accrued' => 'Devengada (por liquidar)', 'settled' => 'Liquidada', 'void' => 'Anulada'];

    public function getStatusOptions(): array { return self::$statuses; }
    public function getPayerOptions(): array { return ['buyer' => 'Comprador', 'organizer' => 'Organizador']; }
    public function getTenantNameAttribute(): ?string
    {
        return class_exists(\Aero\Sites\Models\Tenant::class) ? \Aero\Sites\Models\Tenant::find($this->tenant_id)?->name : null;
    }

    public function beforeDelete(): void { throw new \ApplicationException('El libro de comisiones no se borra: se anula.'); }
}
