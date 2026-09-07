<?php namespace Aero\Crm\Models;

use Model;

class CrmSettings extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_crm_settings';

    public $fillable = [
        'tenant_id', 'is_enabled',
        'collections_enabled', 'reminder_interval_days', 'reminder_message_template',
        'collections_bank_account_id',
    ];

    public $rules = [
        'tenant_id' => 'required|exists:aero_sites_tenants,id|unique:aero_crm_settings,tenant_id',
    ];

    public $belongsTo = [
        'tenant' => [\Aero\Sites\Models\Tenant::class],
    ];

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Cuentas bancarias activas del tenant en aero/qrbo, para el selector de
     * "Cuenta bancaria para cobranzas" — dinámicas y estáticas por igual,
     * ambas soportadas (ver Classes\Collections\CollectionQrIssuer). Vacío
     * si aero/qrbo no está instalado.
     */
    public function getCollectionsBankAccountIdOptions(): array
    {
        if (!class_exists(\Aero\Qrbo\Models\BankAccount::class)) {
            return [];
        }

        return \Aero\Qrbo\Models\BankAccount::active()
            ->where('tenant_id', $this->tenant_id)
            ->pluck('label', 'id')
            ->all();
    }
}
