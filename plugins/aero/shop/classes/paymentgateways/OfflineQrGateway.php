<?php namespace Aero\Shop\Classes\PaymentGateways;

/**
 * Driver "pagos_qr_offline": el vendedor sube una sola imagen de QR estático
 * (el mismo QR de su app bancaria, sin integración) que se muestra igual a
 * todos los compradores. A diferencia de "pagos_qr" (aero/qrbo) no genera
 * nada por pedido ni confirma el pago solo — requires_manual_confirmation
 * es siempre true, igual que el driver manual.
 */
class OfflineQrGateway
{
    public static function label(): string
    {
        return 'Pagos QR (offline, imagen estática)';
    }
}
