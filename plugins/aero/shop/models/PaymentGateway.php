<?php namespace Aero\Shop\Models;

use Aero\Sites\Traits\ResolvesCurrentTenant;
use Model;

class PaymentGateway extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\Sortable;
    use ResolvesCurrentTenant;

    public $table = 'aero_shop_payment_gateways';

    public $fillable = [
        'tenant_id', 'driver', 'name', 'instructions', 'config',
        'is_active', 'sort_order', 'qrbo_bank_account_id',
    ];

    protected $jsonable = ['config'];

    public $rules = [
        'tenant_id'             => 'required|exists:aero_sites_tenants,id',
        'driver'                => 'required|max:50',
        'name'                  => 'required|max:150',
        'qrbo_bank_account_id'  => 'required_if:driver,pagos_qr',
    ];

    public $belongsTo = [
        'tenant' => [\Aero\Sites\Models\Tenant::class],
    ];

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Todas las cuentas bancarias activas del tenant en aero/qrbo —
     * dinámicas (BNB/Banco Económico, generan un QR real por pedido) y
     * estáticas (correo, sin API: reutilizan su QR fijo). `QrIssuer::issue()`
     * decide cuál de los dos casos aplica según el `bank_code` de la cuenta
     * elegida, así que acá no hace falta filtrar. Vacío (con comment
     * explicativo en el field) si aero/qrbo no está instalado.
     */
    public function getQrboBankAccountIdOptions(): array
    {
        if (!class_exists(\Aero\Pay\Models\BankAccount::class)) {
            return [];
        }

        $tenantId = $this->tenant_id ?: $this->getCurrentTenantId();
        if (!$tenantId) {
            return [];
        }

        return \Aero\Pay\Models\BankAccount::active()
            ->where('tenant_id', $tenantId)
            ->pluck('label', 'id')
            ->all();
    }
}
