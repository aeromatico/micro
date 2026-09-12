<?php namespace Aero\Credits\Models;

use Model;

/**
 * Ledger inmutable: cada cobro, recarga o reembolso es una fila nueva, nunca
 * se actualiza una existente. `balance_after` queda congelado al momento del
 * movimiento (para auditoría), aunque la fuente de verdad del saldo actual es
 * SUM(delta) agrupado por tenant_id+credit_type_id.
 */
class CreditTransaction extends Model
{
    public $table = 'aero_credits_transactions';

    public $timestamps = false;

    public $fillable = [
        'tenant_id', 'credit_type_id', 'delta', 'balance_after',
        'action_code', 'source_plugin', 'reason', 'meta',
        'created_by_user_id', 'created_at',
    ];

    public $jsonable = ['meta'];

    protected $dates = ['created_at'];

    protected $casts = [
        'delta'         => 'integer',
        'balance_after' => 'integer',
    ];

    public $attributes = [
        'created_at' => null,
    ];

    public $belongsTo = [
        'creditType' => \Aero\Credits\Models\CreditType::class,
    ];

    public function beforeCreate()
    {
        $this->created_at = $this->created_at ?: now();
    }

    public function getIsChargeAttribute(): bool
    {
        return $this->delta < 0;
    }

    public function getCreditTypeOptions(): array
    {
        return CreditType::active()->pluck('label', 'id')->all();
    }

    public function getActionCodeOptions(): array
    {
        return CreditAction::orderBy('code')->pluck('label', 'code')->all();
    }
}
