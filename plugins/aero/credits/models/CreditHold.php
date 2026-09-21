<?php namespace Aero\Credits\Models;

use Model;

class CreditHold extends Model
{
    public const PENDING  = 'pending';
    public const SETTLED  = 'settled';
    public const REFUNDED = 'refunded';

    public $table = 'aero_credits_holds';

    public $timestamps = false;

    public $fillable = ['transaction_id', 'tenant_id', 'status', 'expires_at', 'resolved_at', 'created_at'];

    protected $dates = ['expires_at', 'resolved_at', 'created_at'];

    public $belongsTo = [
        'transaction' => \Aero\Credits\Models\CreditTransaction::class,
    ];

    public function scopeExpired($query)
    {
        return $query->where('status', self::PENDING)->where('expires_at', '<', now());
    }
}
