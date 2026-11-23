<?php namespace Aero\Social\Classes;

use Aero\Social\Models\PostTarget;

/**
 * Cobro por publicación y por red (`social.post.{red}`), con el costo que el
 * superadmin fija en Créditos → Acciones. Se cobra al programar y se devuelve
 * si la publicación falla o se cancela. Sin Aero.Credits no se cobra nada.
 */
class Billing
{
    public const PREFIX = 'social.post.';

    public static function enabled(): bool
    {
        return class_exists(\Aero\Credits\Classes\Credits::class);
    }

    /** ['type' => CreditType|null, 'amount' => int]; acción inexistente = gratis. */
    public static function cost(string $network): array
    {
        if (!self::enabled()) {
            return ['type' => null, 'amount' => 0];
        }

        try {
            return \Aero\Credits\Classes\Credits::cost(self::PREFIX . $network);
        } catch (\RuntimeException $e) {
            return ['type' => null, 'amount' => 0];
        }
    }

    /** Texto para el formulario: «Facebook: 1 crédito azul · Instagram: …». */
    public static function tariffs(array $networks): array
    {
        $out = [];
        foreach ($networks as $network) {
            $cost = self::cost($network);
            if ($cost['type'] && $cost['amount'] > 0) {
                $out[$network] = $cost['amount'] . ' ' . ($cost['type']->label ?: $cost['type']->code);
            }
        }

        return $out;
    }

    /**
     * @throws \Aero\Credits\Classes\Exceptions\InsufficientCreditsException
     */
    public static function charge(PostTarget $target): void
    {
        if (!$target->tenant_id) {
            return;
        }

        $cost = self::cost($target->network);
        if (!$cost['type'] || $cost['amount'] < 1) {
            return;
        }

        $tx = \Aero\Credits\Classes\Credits::chargeRaw(
            (int) $target->tenant_id,
            $cost['type'],
            (int) $cost['amount'],
            self::PREFIX . $target->network,
            [
                'source_plugin'  => 'Aero.Social',
                'reason'         => 'Publicación en ' . Networks::label($target->network),
                'post_id'        => $target->post_id,
                'post_target_id' => $target->id,
            ]
        );

        $target->credits_charged = (int) $cost['amount'];
        $target->credit_transaction_id = $tx->id;
        $target->save();
    }

    /** Devuelve el cobro una sola vez. */
    public static function refund(PostTarget $target, string $reason): void
    {
        if (!$target->credit_transaction_id || $target->refunded_at || !self::enabled()) {
            return;
        }

        $tx = \Aero\Credits\Models\CreditTransaction::find($target->credit_transaction_id);
        if ($tx) {
            \Aero\Credits\Classes\Credits::refund($tx, $reason);
        }

        $target->refunded_at = now();
        $target->save();
    }
}
