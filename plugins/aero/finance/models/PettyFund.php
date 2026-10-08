<?php namespace Aero\Finance\Models;

use Aero\Finance\Classes\AccountSeeder;
use Aero\Finance\Classes\TenantOwned;
use Model;

/** Un fondo de caja chica = una cuenta de Activo propia: su saldo sale del libro, no de un contador aparte. */
class PettyFund extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use TenantOwned;

    public $table = 'aero_finance_petty_funds';

    public $fillable = ['tenant_id', 'name', 'custodian', 'imprest_amount', 'is_active'];

    public $rules = [
        'name'           => 'required|max:120',
        'imprest_amount' => 'nullable|numeric|min:0',
    ];

    public $attributeNames = ['name' => 'nombre', 'imprest_amount' => 'monto fijo'];

    public $belongsTo = ['account' => [Account::class, 'key' => 'account_id']];

    public $hasMany = ['operations' => [PettyOperation::class, 'key' => 'fund_id']];

    public function beforeCreate(): void
    {
        if (!$this->tenant_id) {
            throw new \ApplicationException('No se pudo determinar el negocio de la caja chica.');
        }

        $this->is_active ??= true;
        AccountSeeder::ensure((int) $this->tenant_id);
        $n = Account::where('tenant_id', $this->tenant_id)->where('code', 'like', '1.1.04.%')->count() + 1;
        $this->account_id = Account::create([
            'tenant_id' => $this->tenant_id,
            'code'      => '1.1.04.' . str_pad((string) $n, 2, '0', STR_PAD_LEFT),
            'name'      => 'Caja chica — ' . $this->name,
            'type'      => 'asset',
            'system_key' => 'petty_cash',
            'is_active' => true,
        ])->id;
    }

    public function beforeDelete(): void
    {
        throw new \ApplicationException('Una caja chica no se elimina; desactívela.');
    }

    /** Saldo según el libro (debe − haber de su cuenta). */
    public function getBalanceAttribute(): float
    {
        if (!$this->account_id) {
            return 0.0;
        }
        $r = JournalLine::where('tenant_id', $this->tenant_id)->where('account_id', $this->account_id)
            ->selectRaw('COALESCE(SUM(debit - credit), 0) as b')->value('b');

        return round((float) $r, 2);
    }
}
