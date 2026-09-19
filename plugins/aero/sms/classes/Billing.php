<?php namespace Aero\Sms\Classes;

use Aero\Sms\Models\Message;

/**
 * Puente hacia Aero.Credits. Soft dependency: sin el plugin (o sin tenant en
 * el mensaje) no se cobra nada y el envío funciona igual.
 */
class Billing
{
    public const ACTION = 'sms.segment';

    public static function enabled(): bool
    {
        return class_exists(\Aero\Credits\Classes\Credits::class);
    }

    /** Créditos que costaría un mensaje de N segmentos, o 0 si no aplica. */
    public static function quote(int $segments): int
    {
        if (!self::enabled()) {
            return 0;
        }

        return (int) \Aero\Credits\Classes\Credits::cost(self::ACTION)['amount'] * $segments;
    }

    /**
     * Cobra el mensaje y anota la transacción en él.
     *
     * @throws \Aero\Credits\Classes\Exceptions\InsufficientCreditsException
     */
    public static function charge(Message $message): void
    {
        if (!self::enabled() || !$message->tenant_id) {
            return;
        }

        $cost = \Aero\Credits\Classes\Credits::cost(self::ACTION);
        $amount = (int) $cost['amount'] * $message->segments;

        if (!$cost['type'] || $amount < 1) {
            return;
        }

        $tx = \Aero\Credits\Classes\Credits::chargeRaw(
            (int) $message->tenant_id,
            $cost['type'],
            $amount,
            self::ACTION,
            [
                'source_plugin' => 'Aero.Sms',
                'reason'        => "SMS a {$message->to} ({$message->segments} seg.)",
                'message_uuid'  => $message->uuid,
                'api_key_id'    => $message->api_key_id,
            ]
        );

        $message->credits_charged = $amount;
        $message->credit_transaction_id = $tx->id;
    }

    /** Devuelve los créditos una sola vez (falló, no se entregó o se canceló). */
    public static function refund(Message $message, string $reason): void
    {
        if (!$message->credit_transaction_id || $message->refunded_at || !self::enabled()) {
            return;
        }

        $tx = \Aero\Credits\Models\CreditTransaction::find($message->credit_transaction_id);

        if (!$tx) {
            return;
        }

        \Aero\Credits\Classes\Credits::refund($tx, $reason);

        $message->refunded_at = now();
        $message->saveQuietly();
    }
}
