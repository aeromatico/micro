<?php namespace Aero\Shopify\Models;

use Model;
use Str;

/** Enlace entre un pedido de Shopify y el QR de aero/pay que lo cobra. */
class Order extends Model
{
    public $table = 'aero_shopify_orders';

    public $fillable = [
        'store_id', 'tenant_id', 'shopify_order_id', 'order_name', 'customer_email',
        'amount', 'currency', 'qr_code_id', 'status', 'error', 'paid_synced_at',
    ];

    public $belongsTo = [
        'store' => [Store::class, 'key' => 'store_id'],
    ];

    public function beforeCreate()
    {
        $this->token = $this->token ?: Str::random(40);
    }

    public function qrCode(): ?\Aero\Pay\Models\QrCode
    {
        if (!$this->qr_code_id || !class_exists(\Aero\Pay\Models\QrCode::class)) {
            return null;
        }

        return \Aero\Pay\Models\QrCode::find($this->qr_code_id);
    }

    public function payUrl(): string
    {
        return url('shopify/pagar/o/' . $this->token);
    }

    public function getStatusOptions(): array
    {
        return [
            'pending'   => 'Esperando pago',
            'paid'      => 'Pagado',
            'cancelled' => 'Cancelado',
            'expired'   => 'Vencido',
            'error'     => 'Error',
            'skipped'   => 'Omitido',
        ];
    }
}
