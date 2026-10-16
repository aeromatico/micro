<?php namespace Aero\Shop\Models;

use Model;

/**
 * Comerciante del modo Marketplace: un miembro del equipo del tenant
 * (Backend\Models\User) con su cuenta de cobro (aero/pay) y su comisión.
 */
class Seller extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_shop_sellers';

    public $fillable = [
        'tenant_id', 'backend_user_id', 'display_name', 'bank_account_id',
        'commission_type', 'commission_value', 'is_active',
    ];

    public $rules = [
        'tenant_id'        => 'required|exists:aero_sites_tenants,id',
        'backend_user_id'  => 'required|integer',
        'display_name'     => 'required|max:120',
        'commission_type'  => 'required|in:percent,fixed',
        'commission_value' => 'required|numeric|min:0',
    ];

    public $belongsTo = [
        'user' => [\Backend\Models\User::class, 'key' => 'backend_user_id'],
    ];

    public $hasMany = [
        'orders'   => [Order::class],
        'products' => [Product::class],
    ];

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function beforeValidate()
    {
        if ($this->commission_type === 'percent' && (float) $this->commission_value > 100) {
            $this->commission_value = 100;
        }
    }

    public function getCommissionTypeOptions(): array
    {
        return ['percent' => 'Porcentaje (%)', 'fixed' => 'Monto fijo por pedido'];
    }

    /** Miembros del equipo del tenant (dueño + TenantUser) que aún no son comerciantes de este registro. */
    public function getBackendUserIdOptions(): array
    {
        $tenantId = $this->tenant_id ?: self::currentTenantId();
        if (!$tenantId) {
            return [];
        }

        $ids = \Aero\Sites\Models\TenantUser::where('tenant_id', $tenantId)->pluck('user_id')->all();
        if ($ownerId = \Aero\Sites\Models\Tenant::where('id', $tenantId)->value('backend_user_id')) {
            $ids[] = $ownerId;
        }

        $taken = self::forTenant($tenantId)->when($this->exists, fn ($q) => $q->where('id', '!=', $this->id))
            ->pluck('backend_user_id')->all();

        return \Backend\Models\User::whereIn('id', array_unique($ids))
            ->whereNotIn('id', $taken)->get()
            ->mapWithKeys(fn ($u) => [$u->id => trim($u->first_name . ' ' . $u->last_name) . ' (' . $u->email . ')'])
            ->all();
    }

    /** Cuentas de cobro activas del tenant. */
    public function getBankAccountIdOptions(): array
    {
        $tenantId = $this->tenant_id ?: self::currentTenantId();
        if (!$tenantId || !class_exists(\Aero\Pay\Models\BankAccount::class)) {
            return [];
        }

        return \Aero\Pay\Models\BankAccount::active()->forTenant($tenantId)->get()
            ->mapWithKeys(fn ($a) => [$a->id => $a->label ?: ($a->bank_code . ' #' . $a->id)])
            ->all();
    }

    /** Comisión del tenant sobre un monto de productos (nunca mayor al monto). */
    public function commissionFor(float $amount): float
    {
        $value = (float) $this->commission_value;
        $commission = $this->commission_type === 'fixed' ? $value : $amount * $value / 100;

        return round(min(max($commission, 0), $amount), 4);
    }

    /** Comerciante activo del usuario en ese tenant, o null. */
    public static function forUser(?\Backend\Models\User $user, int $tenantId): ?self
    {
        if (!$user) {
            return null;
        }

        return self::forTenant($tenantId)->active()->where('backend_user_id', $user->id)->first();
    }

    /** Quién ve todos los comerciantes: superusuario, dueño del tenant o admin del equipo. */
    public static function seesEverything(?\Backend\Models\User $user, int $tenantId): bool
    {
        if (!$user) {
            return false;
        }
        if ($user->is_superuser) {
            return true;
        }
        if (\Aero\Sites\Models\Tenant::where('id', $tenantId)->where('backend_user_id', $user->id)->exists()) {
            return true;
        }

        return \Aero\Sites\Models\TenantUser::where('tenant_id', $tenantId)
            ->where('user_id', $user->id)->where('role', 'admin')->exists();
    }

    /**
     * Restringe una consulta de pedidos/productos en el modo Marketplace: quien no es admin
     * solo ve lo de su propio comerciante, y sin ser comerciante activo no ve nada (falla cerrado).
     * Fuera del modo Marketplace no cambia nada.
     */
    public static function restrictQuery($query, ?\Backend\Models\User $user, ?int $tenantId, string $column = 'seller_id'): void
    {
        if (!$tenantId || !ShopSettings::isMarketplaceForTenant($tenantId) || self::seesEverything($user, $tenantId)) {
            return;
        }

        $seller = self::forUser($user, $tenantId);
        $seller ? $query->where($column, $seller->id) : $query->whereRaw('1 = 0');
    }

    protected static function currentTenantId(): ?int
    {
        $user = \BackendAuth::getUser();

        return $user ? \Aero\Sites\Models\Tenant::resolveForBackendUser($user)?->id : null;
    }
}
