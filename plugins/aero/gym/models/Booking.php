<?php namespace Aero\Gym\Models;

use Aero\Gym\Classes\TenantOwned;
use Model;

class Booking extends Model
{
    use TenantOwned;

    public $table = 'aero_gym_bookings';

    public $fillable = ['tenant_id', 'session_id', 'member_id', 'status', 'booked_at', 'cancelled_at', 'checked_in_at'];

    protected $dates = ['booked_at', 'cancelled_at', 'checked_in_at'];

    public $belongsTo = [
        'session' => [ClassSession::class, 'key' => 'session_id'],
        'member'  => [Member::class, 'key' => 'member_id'],
    ];

    /** Ocupa cupo. */
    public const SEATED = ['booked', 'attended'];

    public function getStatusOptions(): array
    {
        return [
            'booked' => 'Reservada', 'waitlist' => 'En espera', 'cancelled' => 'Cancelada',
            'attended' => 'Asistió', 'no_show' => 'No asistió',
        ];
    }
}
