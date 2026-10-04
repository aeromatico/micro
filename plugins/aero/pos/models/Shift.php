<?php namespace Aero\Pos\Models;

use Model;

class Shift extends Model
{
    public $table = 'aero_pos_shifts';

    public $fillable = [
        'tenant_id', 'terminal_id', 'opened_by_user_id', 'closed_by_user_id', 'opened_at', 'closed_at',
        'opening_cash', 'expected_cash', 'counted_cash', 'difference', 'status', 'notes', 'summary',
    ];

    public $jsonable = ['summary'];

    protected $dates = ['opened_at', 'closed_at'];

    public $belongsTo = [
        'terminal' => [Terminal::class],
        'opener'   => [\Backend\Models\User::class, 'key' => 'opened_by_user_id'],
        'closer'   => [\Backend\Models\User::class, 'key' => 'closed_by_user_id'],
    ];

    public $hasMany = [
        'movements' => [CashMovement::class],
        'payments'  => [Payment::class],
        'sales'     => [Sale::class],
    ];

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
