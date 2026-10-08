<?php namespace Aero\Finance\Models;

use Aero\Finance\Classes\TenantOwned;
use Model;

class JournalLine extends Model
{
    use TenantOwned;

    public $table = 'aero_finance_journal_lines';

    public $fillable = ['tenant_id', 'entry_id', 'account_id', 'debit', 'credit', 'memo'];

    public $belongsTo = [
        'entry'   => [JournalEntry::class, 'key' => 'entry_id'],
        'account' => [Account::class, 'key' => 'account_id'],
    ];

    public function beforeUpdate(): void
    {
        throw new \ApplicationException('Las líneas de un asiento no se editan.');
    }
}
