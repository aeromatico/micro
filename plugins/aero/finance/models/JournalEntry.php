<?php namespace Aero\Finance\Models;

use Aero\Finance\Classes\TenantOwned;
use Model;

/** Asiento publicado: inmutable. Se corrige anulándolo (asiento inverso). */
class JournalEntry extends Model
{
    use TenantOwned;

    public $table = 'aero_finance_journal_entries';

    public $fillable = ['tenant_id', 'number', 'date', 'description', 'status', 'reversal_of_id', 'source_type', 'source_id', 'source_event'];

    protected $dates = ['date'];

    public $hasMany = ['lines' => [JournalLine::class, 'key' => 'entry_id']];

    public function beforeUpdate(): void
    {
        // Solo el estado puede cambiar (posted → void al anular).
        $dirty = array_keys($this->getDirty());
        if (array_diff($dirty, ['status', 'updated_at'])) {
            throw new \ApplicationException('Un asiento publicado no se edita; anúlelo.');
        }
    }

    public function beforeDelete(): void
    {
        throw new \ApplicationException('Un asiento publicado no se elimina; anúlelo.');
    }

    public function getTotalAttribute(): float
    {
        return (float) $this->lines()->sum('debit');
    }
}
