<?php namespace Aero\Producer\Classes;

use Aero\Producer\Models\Order;
use Aero\Producer\Models\Style;
use ApplicationException;

/**
 * Puente hacia Aero.Credits. Dependencia blanda: sin el plugin (o sin tenant en
 * el pedido) no se cobra nada. El monto y el color salen del estilo.
 */
class Billing
{
    public const ACTION = 'producer.video';

    public static function enabled(): bool
    {
        return class_exists(\Aero\Credits\Classes\Credits::class);
    }

    public static function type(Style $style): ?object
    {
        return self::enabled() ? \Aero\Credits\Models\CreditType::findByCode((string) $style->credit_type) : null;
    }

    /** Lo que ve el tenant antes de enviar: costo, color y saldo (null = no aplica). */
    public static function quote(Style $style, ?int $tenantId): ?array
    {
        $type = self::type($style);
        if (!$type || $style->credit_cost < 1) {
            return null;
        }

        return [
            'cost'    => (int) $style->credit_cost,
            'type'    => $type->label ?? $type->code,
            'balance' => $tenantId ? \Aero\Credits\Classes\Credits::balance($tenantId, $type->code) : null,
        ];
    }

    /**
     * Cobra el pedido y anota la transacción en él.
     *
     * @throws ApplicationException si el saldo no alcanza
     */
    public static function charge(Order $order): void
    {
        $style = $order->style;
        $type = self::type($style);

        if (!$type || !$order->tenant_id || $style->credit_cost < 1) {
            return;
        }

        // Ya cobrado y sin reembolsar: reenviar el formulario no vuelve a cobrar.
        if ($order->credit_transaction_id && !$order->refunded_at) {
            return;
        }

        try {
            $tx = \Aero\Credits\Classes\Credits::chargeRaw(
                (int) $order->tenant_id, $type, (int) $style->credit_cost, self::ACTION,
                [
                    'source_plugin'   => 'Aero.Producer',
                    'reason'          => "Video «{$order->name}» ({$style->name})",
                    'order_id'        => $order->id,
                    'style'           => $style->slug,
                    'idempotency_key' => 'producer.order.' . $order->id . '.after.' . (int) $order->credit_transaction_id,
                ]
            );
        } catch (\Aero\Credits\Classes\Exceptions\InsufficientCreditsException $e) {
            throw new ApplicationException(
                "No tienes créditos suficientes: este video cuesta {$e->required} ({$type->label}) y tu saldo es {$e->available}."
            );
        }

        $order->forceFill(['credits_charged' => (int) $style->credit_cost, 'credit_transaction_id' => $tx->id, 'refunded_at' => null])->save();
    }

    /** Devuelve los créditos una sola vez; nunca lanza (no debe tumbar el cambio de estado). */
    public static function refund(Order $order, string $reason): void
    {
        if (!$order->credit_transaction_id || $order->refunded_at || !self::enabled()) {
            return;
        }

        try {
            if ($tx = \Aero\Credits\Models\CreditTransaction::find($order->credit_transaction_id)) {
                \Aero\Credits\Classes\Credits::refund($tx, $reason);
                $order->forceFill(['refunded_at' => now()])->save();
            }
        } catch (\Throwable $e) {
            \Log::error("Aero.Producer: no se pudo devolver el cobro del pedido #{$order->id}: " . $e->getMessage());
            \Aero\Credits\Classes\Credits::recordFailure((int) $order->tenant_id, self::ACTION, 'reembolso: ' . $e->getMessage(), ['order_id' => $order->id]);
        }
    }
}
