<?php namespace Aero\Credits\Models;

use Model;

/**
 * Cuánto de un regalo de plan con vencimiento sigue sin gastarse. Ver la
 * migración create_credit_plan_grant_lots_table.php y Credits::topUp()
 * ($expiresAt) / Credits::chargeRaw() (consumeFromLots()) / el comando
 * credits:expire-plan-grants.
 */
class CreditPlanGrantLot extends Model
{
    public $table = 'aero_credits_plan_grant_lots';

    public $fillable = [
        'tenant_id', 'credit_type_id', 'plan_id', 'transaction_id',
        'granted_amount', 'remaining_amount', 'expires_at', 'expired_at',
    ];

    protected $dates = ['expires_at', 'expired_at'];

    public $belongsTo = [
        'creditType' => [CreditType::class, 'key' => 'credit_type_id'],
    ];

    public function scopeActive($query)
    {
        return $query->whereNull('expired_at')->where('remaining_amount', '>', 0);
    }
}
