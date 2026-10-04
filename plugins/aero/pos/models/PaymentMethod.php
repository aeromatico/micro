<?php namespace Aero\Pos\Models;

use Model;
use Str;

class PaymentMethod extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_pos_payment_methods';

    public $fillable = ['tenant_id', 'code', 'label', 'kind', 'gateway_id', 'is_active', 'sort_order'];

    public const KINDS = [
        'cash'     => 'Efectivo',
        'qr'       => 'QR',
        'card'     => 'Tarjeta',
        'transfer' => 'Transferencia',
        'other'    => 'Otro',
    ];

    public $rules = [
        'tenant_id' => 'required|exists:aero_sites_tenants,id',
        'label'     => 'required|max:60',
        'kind'      => 'required|in:cash,qr,card,transfer,other',
        'code'      => 'nullable|alpha_dash|max:30',
    ];

    public function beforeValidate()
    {
        if (!$this->code && $this->label) {
            $this->code = Str::slug($this->label);
        }
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function isCash(): bool
    {
        return $this->kind === 'cash';
    }
}
