<?php namespace Aero\Invoicing\Models;

use Model;

/**
 * Un contribuyente (NIT) que factura, con su sucursal y punto de venta.
 * Un tenant puede tener varios. Certificado y token del SIN NO se guardan
 * aquí: viven en la credencial del proveedor (provider_credential).
 */
class Issuer extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_invoicing_issuers';

    public $fillable = [
        'tenant_id', 'name', 'nit', 'legal_name', 'trade_name', 'address', 'municipality', 'phone',
        'branch_code', 'pos_code', 'modality', 'environment', 'system_code', 'driver',
        'provider_credential', 'is_active',
    ];

    public $rules = [
        'name'       => 'required',
        'nit'        => 'required|digits_between:5,20',
        'legal_name' => 'required',
        'branch_code' => 'integer|min:0',
        'pos_code'    => 'integer|min:0',
        'modality'    => 'in:1,2',
        'environment' => 'in:test,production',
        'driver'      => 'in:floogos,simulated',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public $hasMany = ['invoices' => Invoice::class];

    /** Fallar cerrado: sin tenant no se ve nada. */
    public function scopeForTenant($query, ?int $tenantId)
    {
        return $tenantId ? $query->where($this->getTable() . '.tenant_id', $tenantId) : $query->whereRaw('1 = 0');
    }

    /** ¿Puede emitir? Con Floogos hace falta la credencial del proveedor. */
    public function getIsReadyAttribute(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        return $this->driver === 'simulated' || filled($this->provider_credential);
    }

    public function getModalityOptions(): array
    {
        return [1 => 'Electrónica en línea (firma digital)', 2 => 'Computarizada en línea'];
    }

    public function getEnvironmentOptions(): array
    {
        return ['test' => 'Pruebas (piloto del SIN)', 'production' => 'Producción'];
    }

    public function getDriverOptions(): array
    {
        return ['floogos' => 'Floogos', 'simulated' => 'Simulado (solo pruebas internas)'];
    }
}
