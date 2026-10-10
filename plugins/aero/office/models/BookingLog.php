<?php namespace Aero\Office\Models;

use Model;

/** Historial inmutable de cambios de una reserva. */
class BookingLog extends Model
{
    use \Aero\Office\Classes\TenantOwned;

    public $table = 'aero_office_booking_logs';

    public $timestamps = false;

    public $fillable = ['tenant_id', 'booking_id', 'user_id', 'actor', 'action', 'changes', 'note', 'created_at'];

    protected $dates = ['created_at'];

    public $jsonable = ['changes'];

    public $belongsTo = ['booking' => [Booking::class, 'key' => 'booking_id']];

    public static function actions(): array
    {
        return [
            'created' => 'Creada', 'confirmed' => 'Confirmada', 'rejected' => 'Rechazada', 'cancelled' => 'Cancelada',
            'rescheduled' => 'Reprogramada', 'reassigned' => 'Reasignada', 'status' => 'Cambio de estado', 'edited' => 'Editada',
        ];
    }

    public function getActionLabelAttribute(): string
    {
        return self::actions()[$this->action] ?? $this->action;
    }
}
