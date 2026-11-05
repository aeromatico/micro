<?php namespace Aero\Tickets\Classes;

use Aero\Tickets\Models\CommissionEntry;
use Aero\Tickets\Models\Order;
use Aero\Tickets\Models\Payment;
use Illuminate\Support\Facades\DB;

/** Libro de comisiones: una entrada por orden cobrada (idempotente) y su anulación si el pago se devuelve. */
class CommissionLedger
{
    public static function accrue(Order $o, ?Payment $p = null): ?CommissionEntry
    {
        if ((float) $o->commission_total <= 0) {
            return null;
        }
        $payers = collect((array) $o->commission_terms)->pluck('payer')->unique();

        try {
            return DB::transaction(function () use ($o, $p, $payers) {
                if ($e = CommissionEntry::where('order_id', $o->id)->lockForUpdate()->first()) {
                    return $e;                                        // repetir la confirmación no duplica
                }

                return CommissionEntry::create([
                    'tenant_id' => $o->tenant_id, 'event_id' => $o->event_id, 'order_id' => $o->id, 'payment_id' => $p?->id, 'currency' => $o->currency,
                    'base_amount' => $o->subtotal, 'commission_amount' => $o->commission_total, 'payer' => $payers->count() === 1 ? $payers->first() : 'mixed',
                    'terms' => $o->commission_terms, 'status' => 'accrued', 'accrued_at' => now(),
                ]);
            });
        } catch (\Illuminate\Database\QueryException $e) {
            return CommissionEntry::where('order_id', $o->id)->first();    // carrera: la otra transacción ya lo creó
        }
    }

    public static function void(Order $o, string $reason): void
    {
        CommissionEntry::where('order_id', $o->id)->where('status', 'accrued')->update(['status' => 'void', 'voided_at' => now(), 'void_reason' => mb_substr($reason, 0, 250)]);
    }
}
