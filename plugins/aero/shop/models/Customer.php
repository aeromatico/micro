<?php namespace Aero\Shop\Models;

use Model;

class Customer extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_shop_customers';

    public $fillable = ['tenant_id', 'user_id', 'email', 'first_name', 'last_name', 'phone', 'is_guest', 'tax_id', 'tax_name'];

    public $rules = [
        'tenant_id' => 'required|exists:aero_sites_tenants,id',
        'email'     => 'required|email|max:255',
    ];

    public $belongsTo = [
        'tenant' => [\Aero\Sites\Models\Tenant::class],
        'user'   => [\RainLab\User\Models\User::class],
    ];

    public $hasMany = [
        'addresses' => [Address::class],
        'orders'    => [Order::class],
    ];

    public function beforeValidate()
    {
        $this->is_guest = is_null($this->user_id);
    }

    /**
     * Opciones de relación en formularios de otros plugins (p.ej. CRM →
     * "Cliente de tienda vinculado"): solo clientes del tenant del registro.
     */
    public function scopeBelongingToTenant($query, $model)
    {
        return $model && $model->tenant_id
            ? $query->where('tenant_id', $model->tenant_id)
            : $query;
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}") ?: $this->email;
    }
}
