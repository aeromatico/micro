<?php namespace Aero\Office\Models;

use Aero\Office\Classes\TenantOwned;
use Model;

/**
 * Cliente del negocio. Privado por tenant: la misma persona en dos negocios
 * son dos registros. user_id (RainLab.User) es opcional — se puede reservar sin cuenta.
 */
class Customer extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;
    use TenantOwned;

    public $table = 'aero_office_customers';

    public $fillable = ['tenant_id', 'user_id', 'crm_contact_id', 'name', 'phone', 'email', 'document', 'notes'];

    public $rules = [
        'name'  => 'required|string|max:255',
        'email' => 'nullable|email|max:255',
    ];

    public $hasMany = ['bookings' => [Booking::class, 'key' => 'customer_id']];

    public function afterCreate(): void
    {
        \Aero\Office\Classes\CrmLink::sync($this);
    }

    /** Busca por correo o teléfono dentro del tenant (reserva pública sin duplicar clientes). */
    public static function findMatch(int $tenantId, ?string $email, ?string $phone): ?self
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        $q = static::where('tenant_id', $tenantId)->where(function ($w) use ($email, $digits) {
            if ($email) {
                $w->orWhere('email', $email);
            }
            if ($digits) {
                $w->orWhere('phone', $digits);
            }
        });

        return ($email || $digits) ? $q->first() : null;
    }

    public function setPhoneAttribute($value): void
    {
        $this->attributes['phone'] = $value === null || $value === '' ? null : preg_replace('/[^\d+]/', '', (string) $value);
    }
}
