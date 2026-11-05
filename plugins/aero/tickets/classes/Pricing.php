<?php namespace Aero\Tickets\Classes;

use Aero\Tickets\Models\TicketType;

/**
 * Precio base, comisión y precio final de una entrada. Las condiciones salen de CommissionResolver
 * (tipo → evento → organizador → plataforma) y se devuelven para guardarlas en la orden.
 * - Comprador paga la comisión: total = base + comisión (el organizador recibe el base).
 * - Organizador la paga: total = base (el organizador recibe base − comisión).
 * Gratuitas y cortesías no llevan comisión.
 */
class Pricing
{
    /** @return array{base: float, fee: float, total: float, net: float, currency: string, fee_payer: string, terms: array} */
    public static function quote(TicketType $t): array
    {
        $base = $t->kind === 'paid' ? round((float) $t->price, 2) : 0.0;
        $terms = CommissionResolver::terms($t);
        $payer = $terms['payer'];
        $fee = $base > 0 ? round($base * $terms['percent'] / 100 + $terms['fixed'], 2) : 0.0;

        return [
            'base' => $base,
            'fee' => $fee,
            'total' => $payer === 'buyer' ? round($base + $fee, 2) : $base,
            'net' => $payer === 'buyer' ? $base : round(max(0, $base - $fee), 2),
            'currency' => $t->currency ?: 'BOB',
            'fee_payer' => $payer,
            'terms' => $terms,
        ];
    }
}
