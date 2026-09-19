<?php namespace Aero\Sms\Http\Controllers\Api;

use Aero\Sms\Classes\Billing;
use Aero\Sms\Classes\Segments;
use Aero\Sms\Models\Message;
use Illuminate\Http\Request;

class UsageController extends ApiController
{
    /** Consumo del consumidor autenticado, por día, en un rango (por defecto 30 días). */
    public function index(Request $request)
    {
        $from = $request->date('from') ?? now()->subDays(30)->startOfDay();
        $to = $request->date('to') ?? now()->endOfDay();

        $rows = $this->scoped(Message::query(), $request)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw("DATE(created_at) as day, COUNT(*) as messages, SUM(segments) as segments,
                SUM(status = 'delivered') as delivered, SUM(status IN ('failed','undelivered')) as failed,
                SUM(CASE WHEN refunded_at IS NULL THEN credits_charged ELSE 0 END) as credits")
            ->groupBy('day')->orderBy('day')->get();

        return response()->json([
            'data' => $rows->map(fn ($r) => [
                'day'       => $r->day,
                'messages'  => (int) $r->messages,
                'segments'  => (int) $r->segments,
                'delivered' => (int) $r->delivered,
                'failed'    => (int) $r->failed,
                'credits'   => (int) $r->credits,
            ]),
            'totals' => [
                'messages' => (int) $rows->sum('messages'),
                'segments' => (int) $rows->sum('segments'),
                'credits'  => (int) $rows->sum('credits'),
            ],
        ]);
    }

    /** Cotiza sin enviar: segmentos, codificación y créditos. */
    public function quote(Request $request)
    {
        $data = $request->validate(['body' => 'required|string|max:1600', 'recipients' => 'nullable|integer|min:1']);
        $count = Segments::count($data['body']);
        $n = (int) ($data['recipients'] ?? 1);

        return response()->json(['data' => $count + [
            'recipients'      => $n,
            'credits_per_sms' => Billing::quote($count['segments']),
            'credits_total'   => Billing::quote($count['segments']) * $n,
        ]]);
    }
}
