<?php namespace Aero\Finance\Models;

use Aero\Finance\Classes\TenantOwned;
use Model;

class Movement extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use TenantOwned;

    public $table = 'aero_finance_movements';

    public $fillable = [
        'tenant_id', 'kind', 'date', 'amount', 'currency', 'exchange_rate', 'category_account_id',
        'cash_account_id', 'description', 'counterparty', 'nit', 'document_no', 'tax_amount',
    ];

    protected $dates = ['date'];

    public $rules = [
        'kind'                => 'required|in:income,expense',
        'date'                => 'required|date',
        'amount'              => 'required|numeric|gt:0',
        'currency'            => 'required|size:3',
        'category_account_id' => 'required',
        'cash_account_id'     => 'required',
        'description'         => 'required|max:255',
        'tax_amount'          => 'nullable|numeric|min:0',
    ];

    public $attributeNames = [
        'amount' => 'monto', 'date' => 'fecha', 'category_account_id' => 'categoría',
        'cash_account_id' => 'cuenta de cobro/pago', 'description' => 'descripción',
    ];

    public $belongsTo = [
        'category' => [Account::class, 'key' => 'category_account_id'],
        'cash'     => [Account::class, 'key' => 'cash_account_id'],
        'entry'    => [JournalEntry::class, 'key' => 'entry_id'],
    ];

    public function getKindOptions(): array
    {
        return ['income' => 'Ingreso', 'expense' => 'Egreso'];
    }

    public function getCurrencyOptions(): array
    {
        return ['BOB' => 'Bolivianos (BOB)', 'USD' => 'Dólares (USD)'];
    }

    public function getCategoryAccountIdOptions(): array
    {
        $type = ($this->kind ?: 'income') === 'expense' ? 'expense' : 'income';

        return Account::visible()->where('type', $type)->where('is_active', true)->orderBy('code')
            ->get()->pluck('label', 'id')->all();
    }

    public function getCashAccountIdOptions(): array
    {
        return Account::visible()->where('type', 'asset')->where('is_active', true)->orderBy('code')
            ->get()->pluck('label', 'id')->all();
    }

    /** Antes de crear: valida que las cuentas sean del tenant. */
    public function beforeCreate(): void
    {
        $this->assertReferencesVisible(['category_account_id' => Account::class, 'cash_account_id' => Account::class]);
    }

    /** El asiento nace con el movimiento; si falla, el movimiento no queda huérfano. */
    public function afterCreate(): void
    {
        try {
            app(\Aero\Finance\Classes\MovementService::class)->postEntry($this);
        } catch (\Throwable $e) {
            static::query()->whereKey($this->id)->toBase()->delete();
            throw $e;
        }
    }

    public function beforeUpdate(): void
    {
        $dirty = array_keys($this->getDirty());
        if (array_diff($dirty, ['status', 'entry_id', 'updated_at'])) {
            throw new \ApplicationException('Un movimiento registrado no se edita; anúlelo y cree otro.');
        }
    }

    public function beforeDelete(): void
    {
        throw new \ApplicationException('Un movimiento no se elimina; anúlelo.');
    }
}
