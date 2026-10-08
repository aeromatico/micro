<?php namespace Aero\Pos\Models;

use Hash;
use Model;

class Cashier extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_pos_cashiers';

    public $fillable = ['tenant_id', 'user_id', 'discount_limit_percent', 'is_supervisor', 'is_active'];

    protected $hidden = ['pin_hash'];

    public $rules = [
        'tenant_id'              => 'required|exists:aero_sites_tenants,id',
        'user_id'                => 'required',
        'discount_limit_percent' => 'nullable|integer|min:0|max:100',
    ];

    public $belongsTo = [
        'user' => [\Backend\Models\User::class, 'key' => 'user_id'],
    ];

    /** Campo virtual del formulario: PIN de 4 a 6 dígitos (se guarda con hash, nunca en claro). */
    public $pin_input;

    public function beforeSave()
    {
        $pin = trim((string) $this->pin_input);
        if ($pin !== '') {
            if (!preg_match('/^\d{4,6}$/', $pin)) {
                throw new \ValidationException(['pin_input' => 'El PIN debe tener de 4 a 6 dígitos.']);
            }
            $this->pin_hash = Hash::make($pin);
        }
        unset($this->attributes['pin_input']);
    }

    public function checkPin(string $pin): bool
    {
        return $this->pin_hash && Hash::check($pin, $this->pin_hash);
    }

    public function getHasPinAttribute(): bool
    {
        return (bool) $this->pin_hash;
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /** Usuarios de backend con acceso al tenant: el dueño y los miembros. */
    public function getUserIdOptions(): array
    {
        $tenant = \Aero\Sites\Models\Tenant::find($this->tenant_id);
        if (!$tenant) {
            return [];
        }
        $ids = \Aero\Sites\Models\TenantUser::where('tenant_id', $tenant->id)->pluck('user_id')->push($tenant->backend_user_id)->filter()->unique();

        return \Backend\Models\User::whereIn('id', $ids)->orderBy('first_name')->get()
            ->mapWithKeys(fn ($u) => [$u->id => trim($u->first_name . ' ' . $u->last_name) . ' (' . $u->email . ')'])->all();
    }
}
