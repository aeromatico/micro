<?php namespace Aero\Shop\Classes\PaymentGateways;

use Aero\Shop\Models\Order;
use Aero\Shop\Models\PaymentGateway;

/**
 * Driver "pagos_qr": usa la cuenta que el tenant conectó en aero/qrbo — un
 * banco QR (BNB/Banco Económico), un correo (QR fijo, sin API) o, desde que
 * aero/qrbo agregó flow() === 'redirect', PayPal/NOWPayments. Con un
 * proveedor con getStatus()/webhook real el pedido se marca "paid"
 * automáticamente cuando aero/qrbo confirma el pago (ver
 * Plugin::bootQrboPaymentBridge()). Con una cuenta 'qr_static' (correo, sin
 * API) no hay confirmación automática, el vendedor marca el pedido pagado a
 * mano (ver QrIssuer::issue()).
 */
class PagosQrGateway
{
    public static function label(): string
    {
        return 'Pagos QR (aero/qrbo)';
    }

    /**
     * Emite el intento de cobro para el monto del pedido y guarda su
     * referencia interna en `order.payment_reference`, que es lo que el
     * listener de `aero.qrbo.paymentReceived` usa para encontrar el pedido a
     * marcar como pagado. Lanza RuntimeException (nunca tipos de aero/qrbo)
     * para que Checkout no necesite conocer esa dependencia opcional.
     */
    public static function issueForOrder(PaymentGateway $gateway, Order $order): void
    {
        if (!class_exists(\Aero\Qrbo\Classes\QrIssuer::class)) {
            throw new \RuntimeException('El plugin de Pagos QR no está disponible en este momento.');
        }

        $bankAccount = \Aero\Qrbo\Models\BankAccount::active()
            ->where('tenant_id', $order->tenant_id)
            ->find($gateway->qrbo_bank_account_id);

        if (!$bankAccount) {
            throw new \RuntimeException('La cuenta configurada para Pagos QR ya no está disponible.');
        }

        // Página de confirmación del pedido — a dónde vuelve el pagador tras
        // aprobar/cancelar en el sitio de un proveedor de flow() 'redirect'
        // (PayPal, NOWPayments). Los bancos QR/correo la ignoran.
        $confirmationUrl = '/tienda/pedido/' . $order->access_token;

        try {
            $qrCode = app(\Aero\Qrbo\Classes\QrIssuer::class)->issue(
                bankAccount: $bankAccount,
                amount: (float) $order->grand_total,
                currency: $order->currency?->code ?: 'BOB',
                description: 'Pedido ' . $order->order_number,
                externalReference: $order->order_number,
                origin: 'shop',
                returnUrl: $confirmationUrl,
                cancelUrl: $confirmationUrl,
            );
        } catch (\Aero\Qrbo\Classes\Exceptions\QrboException $e) {
            throw new \RuntimeException('No se pudo generar el pago: ' . $e->getMessage(), 0, $e);
        }

        $order->payment_reference = $qrCode->internal_reference;
        $order->save();
    }
}
