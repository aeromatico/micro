<?php namespace Aero\Pos\Classes;

use Aero\Pos\Models\Payment;
use Aero\Pos\Models\Sale;
use Aero\Pos\Models\Shift;

/**
 * Reporte de un turno (el «Z» al cerrar y el «X» en curso): ventas, cobros por
 * método, descuentos, propinas, anulaciones y movimientos de efectivo.
 */
class ShiftReport
{
    public static function build(Shift $shift): array
    {
        $payments = Payment::where('shift_id', $shift->id)->where('status', 'completed')->with('method')->get();

        $byMethod = $payments->groupBy('payment_method_id')->map(function ($rows) {
            $method = $rows->first()->method;

            return [
                'label' => $method?->label ?? 'Otro',
                'kind'  => $method?->kind ?? 'other',
                'count' => $rows->count(),
                'total' => round((float) $rows->sum('amount'), 2),
            ];
        })->values()->all();

        $sales = Sale::where('shift_id', $shift->id)->with('order')->get();
        $valid = $sales->filter(fn ($s) => $s->order && !in_array($s->order->status, ['cancelled', 'refunded'], true));
        $voided = $sales->filter(fn ($s) => $s->order && in_array($s->order->status, ['cancelled', 'refunded'], true));

        $in = (float) $shift->movements()->where('type', 'in')->sum('amount');
        $out = (float) $shift->movements()->where('type', 'out')->sum('amount');

        return [
            'shift_id'       => $shift->id,
            'opened_at'      => $shift->opened_at?->toDateTimeString(),
            'closed_at'      => $shift->closed_at?->toDateTimeString(),
            'opening_cash'   => round((float) $shift->opening_cash, 2),
            'sales_count'    => $valid->count(),
            'sales_total'    => round((float) $valid->sum(fn ($s) => (float) $s->order->grand_total), 2),
            'discounts'      => round((float) $valid->sum(fn ($s) => (float) $s->order->discount_total), 2),
            'tips'           => round((float) $valid->sum('tip_total'), 2),
            'voided_count'   => $voided->count(),
            'voided_total'   => round((float) $voided->sum(fn ($s) => (float) $s->order->grand_total), 2),
            'by_method'      => $byMethod,
            'paid_total'     => round((float) $payments->sum('amount'), 2),
            'cash_in'        => round($in, 2),
            'cash_out'       => round($out, 2),
            'expected_cash'  => round((new ShiftService())->expectedCash($shift), 2),
            'counted_cash'   => $shift->counted_cash !== null ? round((float) $shift->counted_cash, 2) : null,
            'difference'     => $shift->difference !== null ? round((float) $shift->difference, 2) : null,
        ];
    }
}
