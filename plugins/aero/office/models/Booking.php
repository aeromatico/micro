<?php namespace Aero\Office\Models;

use Aero\Office\Classes\TenantOwned;
use Model;

class Booking extends Model
{
    use TenantOwned;

    public $table = 'aero_office_bookings';

    /** Estados que ocupan la agenda del profesional. */
    public const BLOCKING = ['pending', 'confirmed', 'in_service', 'completed'];

    /** Estados finales: no admiten más cambios. */
    public const FINAL = ['completed', 'cancelled', 'rejected', 'no_show'];

    public $fillable = [
        'tenant_id', 'code', 'manage_token', 'branch_id', 'service_id', 'worker_id', 'customer_id', 'starts_at', 'ends_at',
        'blocks_until', 'status', 'source', 'service_name', 'duration_minutes', 'price', 'currency', 'worker_name',
        'customer_notes', 'internal_notes', 'confirmed_at', 'cancelled_at', 'cancel_reason', 'reminder_sent_at',
    ];

    protected $dates = ['starts_at', 'ends_at', 'blocks_until', 'confirmed_at', 'cancelled_at', 'reminder_sent_at'];

    public $belongsTo = [
        'branch'   => [Branch::class, 'key' => 'branch_id'],
        'service'  => [Service::class, 'key' => 'service_id'],
        'worker'   => [Worker::class, 'key' => 'worker_id'],
        'customer' => [Customer::class, 'key' => 'customer_id'],
    ];

    public $hasMany = ['logs' => [BookingLog::class, 'key' => 'booking_id']];

    public static function statuses(): array
    {
        return [
            'pending' => 'Pendiente', 'confirmed' => 'Confirmada', 'in_service' => 'En atención', 'completed' => 'Completada',
            'cancelled' => 'Cancelada', 'rejected' => 'Rechazada', 'no_show' => 'No asistió',
        ];
    }

    public function getStatusOptions(): array
    {
        return self::statuses();
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statuses()[$this->status] ?? (string) $this->status;
    }

    public function isFinal(): bool
    {
        return in_array($this->status, self::FINAL, true);
    }

    public function scopeBlocking($q)
    {
        return $q->whereIn($this->getTable() . '.status', self::BLOCKING);
    }

    /** Un profesional solo ve lo suyo, salvo que tenga acceso de recepción/administración. */
    public function scopeVisibleToUser($query)
    {
        $query = $this->scopeVisible($query);
        $user = \BackendAuth::getUser();
        if (!$user || \Aero\Office\Classes\CurrentTenant::isAdmin() || $user->hasAccess('aero.office.use') || $user->hasAccess('aero.office.reception')) {
            return $query;
        }

        $ids = Worker::where('user_id', $user->id)->pluck('id');

        return $query->whereIn($this->getTable() . '.worker_id', $ids->all() ?: [0]);
    }

    public function getCustomerIdOptions(): array
    {
        return Customer::visible()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function getBranchIdOptions(): array
    {
        return Branch::visible()->active()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function getServiceIdOptions(): array
    {
        return Service::visible()->active()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function getWorkerIdOptions(): array
    {
        return ['' => '— Cualquiera disponible —'] + Worker::visible()->active()->orderBy('name')->pluck('name', 'id')->all();
    }
}
