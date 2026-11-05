<?php namespace Aero\Tickets\Models;

use Model;

/** Cómo cobra cada tenant sus entradas. Sin secretos: la cuenta bancaria vive en aero/pay. */
class PaymentSetting extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \Aero\Tickets\Classes\TenantScoped;

    public $table = 'aero_tickets_payment_settings';
    public $fillable = ['tenant_id', 'qr_enabled', 'qr_bank_account_id', 'transfer_enabled', 'transfer_instructions', 'proof_policy', 'general_instructions'];

    public $rules = [
        'proof_policy' => 'in:none,optional,required',
        'transfer_instructions' => 'nullable|string|max:2000',
        'general_instructions' => 'nullable|string|max:2000',
    ];

    public function getProofPolicyOptions(): array
    {
        return ['none' => 'No pedir comprobante', 'optional' => 'Comprobante opcional', 'required' => 'Comprobante obligatorio'];
    }

    public function getTenantIdOptions(): array
    {
        return class_exists(\Aero\Sites\Models\Tenant::class) ? \Aero\Sites\Models\Tenant::orderBy('name')->pluck('name', 'id')->all() : [];
    }

    /** Cuentas de aero/pay activas del tenant (dependencia blanda). */
    public function getQrBankAccountIdOptions(): array
    {
        if (!class_exists(\Aero\Pay\Models\BankAccount::class) || !$this->tenant_id) {
            return [];
        }

        return \Aero\Pay\Models\BankAccount::active()->forTenant((int) $this->tenant_id)->get()
            ->mapWithKeys(fn ($a) => [$a->id => ($a->label ?: ('Cuenta #' . $a->id)) . ' (' . ($a->bank_code ?? '') . ')'])->all();
    }

    public static function forTenant(?int $tenantId): self
    {
        return static::where('tenant_id', $tenantId)->first() ?: new static(['tenant_id' => $tenantId, 'proof_policy' => 'optional']);
    }

    public function beforeSave(): void
    {
        if ($this->qr_bank_account_id === '') {
            $this->qr_bank_account_id = null;
        }
        // La cuenta debe ser de este tenant (id manipulado en el POST).
        if ($this->qr_bank_account_id && class_exists(\Aero\Pay\Models\BankAccount::class)
            && !\Aero\Pay\Models\BankAccount::active()->forTenant((int) $this->tenant_id)->whereKey($this->qr_bank_account_id)->exists()) {
            throw new \ApplicationException('Esa cuenta bancaria no pertenece a este espacio de trabajo.');
        }
        if (\BackendAuth::getUser() && !\Aero\Tickets\Classes\CurrentTenant::isAdmin()) {
            $this->tenant_id = \Aero\Tickets\Classes\CurrentTenant::id();
        }
        if (!$this->tenant_id) {
            throw new \ApplicationException('Falta el espacio de trabajo.');
        }
    }
}
