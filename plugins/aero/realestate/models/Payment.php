<?php namespace Aero\Realestate\Models;

use Model;

/** Movimiento de capital de un anticrético: entrega (anticresista → propietario) o devolución (propietario → anticresista). */
class Payment extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \Aero\Realestate\Classes\TenantOwned;

    public $table = 'aero_realestate_payments';

    public $fillable = ['tenant_id', 'contract_id', 'type', 'amount', 'currency', 'paid_on', 'method', 'reference', 'notes'];

    public $rules = [
        'contract_id' => 'required|integer',
        'type'        => 'required|in:delivery,return',
        'amount'      => 'required|numeric|min:0.01',
        'paid_on'     => 'required|date',
    ];

    public $dates = ['paid_on'];

    public $belongsTo = ['contract' => [Contract::class, 'key' => 'contract_id']];

    public $attachOne = ['receipt' => \System\Models\File::class];

    public static array $types = ['delivery' => 'Entrega de capital', 'return' => 'Devolución de capital'];

    public static array $methods = ['cash' => 'Efectivo', 'transfer' => 'Transferencia', 'check' => 'Cheque', 'qr' => 'QR', 'other' => 'Otro'];

    public function getTypeOptions(): array
    {
        return self::$types;
    }

    public function getMethodOptions(): array
    {
        return self::$methods;
    }

    public function beforeValidate(): void
    {
        if ($c = Contract::find($this->contract_id)) {
            $this->tenant_id = $c->tenant_id;
            $this->currency = $c->currency; // el libro del contrato va en su moneda
        }
    }

    public function beforeSave(): void
    {
        $this->assertReferencesVisible(['contract_id' => Contract::class]);

        $contract = Contract::findOrFail($this->contract_id);
        if (in_array($contract->status, ['draft', 'cancelled'], true) && $this->type === 'return') {
            throw new \ApplicationException('No hay capital entregado para devolver en un contrato ' . mb_strtolower($contract->status_name) . '.');
        }
        if ($this->type === 'delivery') {
            $delivered = (float) static::where('contract_id', $contract->id)->where('type', 'delivery')->when($this->exists, fn ($q) => $q->where('id', '!=', $this->id))->sum('amount');
            if ($delivered + (float) $this->amount > (float) $contract->capital + 0.001) {
                throw new \ApplicationException('Las entregas superan el capital pactado del contrato.');
            }
        }
        if ($this->type === 'return') {
            $delivered = (float) static::where('contract_id', $contract->id)->where('type', 'delivery')->sum('amount');
            $returned = (float) static::where('contract_id', $contract->id)->where('type', 'return')->when($this->exists, fn ($q) => $q->where('id', '!=', $this->id))->sum('amount');
            if ($returned + (float) $this->amount > $delivered + 0.001) {
                throw new \ApplicationException('La devolución supera el capital entregado.');
            }
        }
    }

    public function afterSave(): void
    {
        $contract = Contract::find($this->contract_id);
        if ($contract) {
            app(\Aero\Realestate\Classes\AnticreticoService::class)->recalc($contract);
            if ($this->wasRecentlyCreated || $this->wasChanged('amount')) {
                ContractEvent::log($contract, $this->type === 'return' ? 'return' : 'delivery',
                    ($this->type === 'return' ? 'Devolución' : 'Entrega') . ' de capital: ' . number_format((float) $this->amount, 2, ',', '.') . ' ' . $this->currency . '.');
            }
        }
    }

    public function afterDelete(): void
    {
        if ($contract = Contract::find($this->contract_id)) {
            app(\Aero\Realestate\Classes\AnticreticoService::class)->recalc($contract);
            ContractEvent::log($contract, 'payment_deleted', 'Se eliminó un movimiento de ' . number_format((float) $this->amount, 2, ',', '.') . ' ' . $this->currency . '.');
        }
    }
}
