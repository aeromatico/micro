<?php namespace Aero\Finance\Models;

use Aero\Finance\Classes\TenantOwned;
use Model;

class PettyOperation extends Model
{
    use TenantOwned;

    public $table = 'aero_finance_petty_operations';

    public const KINDS = ['fund' => 'Fondeo / reposición', 'return' => 'Devolución', 'count' => 'Arqueo'];

    public $fillable = ['tenant_id', 'fund_id', 'kind', 'date', 'amount', 'difference', 'counter_account_id', 'description', 'status', 'entry_id'];

    protected $dates = ['date'];

    public $belongsTo = [
        'fund'  => [PettyFund::class, 'key' => 'fund_id'],
        'entry' => [JournalEntry::class, 'key' => 'entry_id'],
    ];

    public function beforeUpdate(): void
    {
        if (array_diff(array_keys($this->getDirty()), ['status', 'updated_at'])) {
            throw new \ApplicationException('Una operación de caja chica no se edita.');
        }
    }

    public function beforeDelete(): void
    {
        throw new \ApplicationException('Una operación de caja chica no se elimina.');
    }
}
