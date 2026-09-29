<?php namespace Aero\Credits\Models;

use Model;

/** Regalo de suscripción pagado por QR; ver Aero\Credits\Classes\Gifts. */
class CreditGift extends Model
{
    public const PENDING = 'pending';
    public const PAID = 'paid';
    public const EXPIRED = 'expired';

    public $table = 'aero_credits_gifts';

    public $fillable = [
        'plan_id', 'period_unit', 'period_count', 'amount_bob', 'buyer_name', 'buyer_contact',
        'recipient_channel', 'recipient', 'recipient_name', 'message', 'status', 'qr_code_id',
        'payment_reference', 'coupon_id', 'expires_at', 'paid_at', 'delivered_at',
    ];

    protected $dates = ['expires_at', 'paid_at', 'delivered_at'];

    protected $casts = ['amount_bob' => 'float', 'period_count' => 'integer'];

    public $belongsTo = [
        'coupon' => [CreditCoupon::class],
    ];
}
