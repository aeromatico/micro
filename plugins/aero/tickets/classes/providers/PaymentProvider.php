<?php namespace Aero\Tickets\Classes\Providers;

use Aero\Tickets\Models\Order;
use Aero\Tickets\Models\Payment;

/**
 * Contrato de un canal de pago en línea (QR de aero/pay hoy; tarjetas u otros proveedores mañana).
 * El proceso de órdenes y tickets NO cambia al sumar uno: el proveedor solo (1) se ofrece para una
 * orden, (2) crea el intento y (3) avisa el cobro llamando a PaymentService::confirmFromProvider().
 * Se registran con el evento `aero.tickets.registerPaymentProviders` (devuelve clases).
 */
interface PaymentProvider
{
    /** Clave estable, también en payments.provider (p. ej. pay_qr). */
    public function key(): string;

    /** Método que ve el comprador (payments.method: qr | card …). */
    public function method(): string;

    public function label(): string;

    public function isAvailable(Order $order): bool;

    /** Crea el cobro en el proveedor y devuelve ['provider_ref' => …, 'view' => datos para mostrar]. */
    public function start(Order $order, Payment $payment): array;

    /** Datos de presentación (QR, enlace de pago…) de un intento ya creado. */
    public function present(Payment $payment): array;
}
