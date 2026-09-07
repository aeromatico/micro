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
        'is_active', 'requires_manual_confirmation', 'sort_order',
        'qrbo_bank_account_id',
    ];

    protected $jsonable = ['config'];

    public $rules = [
        'tenant_id' => 'required|exists:aero_sites_tenants,id',
        'driver'    => 'required|max:50',
        'name'      => 'required|max:150',
    ];

    public $belongsTo = [
        'tenant' => [\Aero\Sites\Models\Tenant::class],
    ];

    /**
     * Solo usada por el driver pagos_qr_offline: una imagen estática (el QR
     * fijo del comercio) que se muestra a todos los compradores, sin
     * generar nada por pedido — a diferencia de pagos_qr (aero/qrbo).
     */
    public $attachOne = [
        'qr_image' => \System\Models\File::class,
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
     * El driver pagos_qr confirma el pago automáticamente vía el evento
     * aero.qrbo.paymentReceived — nunca depende de que el vendedor lo
     * marque a mano, sin importar lo que haya quedado guardado en el campo.
     */
    public function beforeSave()
    {
        if ($this->driver === 'pagos_qr') {
            $this->requires_manual_confirmation = false;
        }
    }

    /**
     * Cuentas bancarias activas del tenant actual en aero/qrbo, para el
     * dropdown de configuración del driver pagos_qr. Vacío (con comment
     * explicativo) si el plugin no está instalado.
     */
    public function getQrboBankAccountIdOptions(): array
    {
        if (!class_exists(\Aero\Qrbo\Models\BankAccount::class)) {
            return [];
        }

        $tenantId = $this->tenant_id ?: $this->getCurrentTenantId();
        if (!$tenantId) {
            return [];
        }

        return \Aero\Qrbo\Models\BankAccount::active()
            ->where('tenant_id', $tenantId)
            ->pluck('label', 'id')
            ->all();
    }
}
