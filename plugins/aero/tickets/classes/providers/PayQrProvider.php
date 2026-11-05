<?php namespace Aero\Tickets\Classes\Providers;

use Aero\Tickets\Models\Order;
use Aero\Tickets\Models\Payment;
use Aero\Tickets\Models\PaymentSetting;

/**
 * QR bancario vía aero/pay (dependencia blanda). Cuenta con API → el cobro se confirma solo
 * (aero.pay.paymentReceived). Cuenta de QR fijo → queda para verificación manual.
 */
class PayQrProvider implements PaymentProvider
{
    public function key(): string { return 'pay_qr'; }
    public function method(): string { return 'qr'; }
    public function label(): string { return 'QR bancario'; }

    protected function account(Order $order)
    {
        if (!class_exists(\Aero\Pay\Classes\QrIssuer::class)) {
            return null;
        }
        $s = PaymentSetting::forTenant($order->tenant_id);
        if (!$s->qr_enabled || !$s->qr_bank_account_id) {
            return null;
        }

        return \Aero\Pay\Models\BankAccount::active()->forTenant((int) $order->tenant_id)->find($s->qr_bank_account_id);
    }

    public function isAvailable(Order $order): bool
    {
        return (bool) $this->account($order);
    }

    public function start(Order $order, Payment $payment): array
    {
        $account = $this->account($order);
        if (!$account) {
            throw new \ApplicationException('El cobro por QR no está disponible en este momento.');
        }

        try {
            $qr = app(\Aero\Pay\Classes\QrIssuer::class)->issue(
                bankAccount: $account,
                amount: (float) $payment->amount,
                currency: $payment->currency,
                description: 'Entradas · orden ' . $order->code,
                externalReference: 'TKT-' . $order->code . '-' . $payment->id,
                origin: 'tickets',
                dueDate: ($order->expires_at ?: now()->addDay())->toDateString(),
                tenantId: (int) $order->tenant_id,
            );
        } catch (\Throwable $e) {
            \Log::warning('aero.tickets: no se pudo emitir el QR: ' . $e->getMessage());
            throw new \ApplicationException('No pudimos generar el QR. Intenta con otro método o más tarde.');
        }

        return ['provider_ref' => $qr->internal_reference, 'view' => ['automatic' => $qr->flow !== 'qr_static']];
    }

    public function present(Payment $payment): array
    {
        $qr = class_exists(\Aero\Pay\Models\QrCode::class)
            ? \Aero\Pay\Models\QrCode::where('tenant_id', $payment->tenant_id)->where('internal_reference', $payment->provider_ref)->first()
            : null;
        $img = $qr?->qr_image;
        $mime = !$img ? null : (str_starts_with($img, 'iVBOR') ? 'image/png' : (str_starts_with($img, '/9j/') ? 'image/jpeg' : (str_starts_with($img, 'PHN2') ? 'image/svg+xml' : 'image/png')));

        return [
            'image' => $img ? 'data:' . $mime . ';base64,' . $img : null,
            'automatic' => $qr ? $qr->flow !== 'qr_static' : false,
            'bank' => $qr?->bank_code,
        ];
    }
}
