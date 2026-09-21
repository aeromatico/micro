<?php namespace Aero\Credits\Models;

use Model;

/**
 * Recarga de monedas pagada con QR. `lines` es una foto del precio al momento
 * de la compra: [{credit_type_id, code, label, bob, price_bob, coins, rounding_bob}].
 * Las monedas solo se acreditan (Recharges::settle) cuando el pago se confirma.
 */
class CreditPurchase extends Model
{
    public const PENDING = 'pending';
    public const PAID = 'paid';
    public const EXPIRED = 'expired';
    public const CANCELLED = 'cancelled';
    public const REVIEW = 'review'; // llegó un pago por menos del monto: revisión manual

    public $table = 'aero_credits_purchases';

    public $fillable = [
        'tenant_id', 'amount_bob', 'status', 'lines', 'qr_code_id', 'payment_reference',
        'expires_at', 'paid_at', 'created_by_user_id',
    ];

    public $jsonable = ['lines'];

    protected $dates = ['expires_at', 'paid_at'];

    protected $casts = ['amount_bob' => 'float'];

    public function scopeExpirable($query)
    {
        return $query->where('status', self::PENDING)->where('expires_at', '<', now());
    }

    public function totalCoins(): int
    {
        return (int) collect($this->lines)->sum('coins');
    }
}
