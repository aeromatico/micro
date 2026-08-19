<?php namespace Aero\Shop\Classes\PaymentGateways;

/**
 * Driver "manual": el comprador paga fuera de línea (transferencia, QR, WhatsApp)
 * y el vendedor confirma el pago desde el backend. requires_manual_confirmation
 * es siempre true para este driver.
 */
class ManualPaymentGateway
{
    public static function label(): string
    {
        return 'Pago manual (instrucciones)';
    }
}
