<?php namespace Aero\Shop\Classes\PaymentGateways;

/**
 * Driver "manual" (Pagos offline): el comprador paga fuera de línea
 * (transferencia, QR fijo, WhatsApp) y el vendedor confirma el pedido a mano
 * desde el backend — sin importar si el gateway tiene o no una cuenta QRBO
 * asociada (ver PaymentGateway::qrbo_bank_account).
 */
class ManualPaymentGateway
{
    public static function label(): string
    {
        return 'Pagos offline';
    }
}
