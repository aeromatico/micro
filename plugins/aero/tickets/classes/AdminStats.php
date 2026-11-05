<?php namespace Aero\Tickets\Classes;

use Aero\Tickets\Models\Buyer;
use Aero\Tickets\Models\CommissionEntry;
use Aero\Tickets\Models\Event;
use Aero\Tickets\Models\Order;
use Aero\Tickets\Models\Organizer;
use Aero\Tickets\Models\Payment;
use Aero\Tickets\Models\Scan;
use Aero\Tickets\Models\Ticket;
use Illuminate\Support\Facades\DB;

/** Indicadores del panel global (todo se calcula en el momento: no hay contadores que se desactualicen). */
class AdminStats
{
    protected static function money($q, string $col = 'total'): array
    {
        return $q->selectRaw("currency, SUM({$col}) t")->groupBy('currency')->pluck('t', 'currency')->map(fn ($v) => (float) $v)->all();
    }

    public static function overview(): array
    {
        $now = now();
        $backendUsers = \Backend\Models\User::count();
        $staff = \Backend\Models\User::whereHas('role', fn ($r) => $r->where('code', 'like', 'tickets_%'))->count() + \Backend\Models\User::where('is_superuser', true)->count();

        $events = [
            'published' => Event::whereIn('status', ['published', 'ongoing'])->count(),
            'active' => Event::whereIn('status', ['published', 'ongoing'])->where('moderation', 'ok')->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now))->count(),
            'finished' => Event::where('status', 'finished')->count(),
            'cancelled' => Event::where('status', 'cancelled')->count(),
            'draft' => Event::where('status', 'draft')->count(),
            'in_review' => Event::where('moderation', 'pending')->count(),
            'suspended' => Event::where('moderation', 'suspended')->count(),
        ];

        $issued = Ticket::whereIn('status', ['valid', 'used'])->count();
        $sold = Ticket::whereIn('status', ['valid', 'used'])->where('amount', '>', 0)->count();
        $pay = Payment::query();

        return [
            'users' => ['backend' => $backendUsers, 'staff' => $staff, 'buyers' => Buyer::count(), 'buyers_blocked' => Buyer::where('status', 'blocked')->count(), 'total' => $backendUsers + Buyer::count()],
            'organizers' => ['total' => Organizer::count(), 'pending' => Organizer::where('status', 'pending')->count(), 'approved' => Organizer::where('status', 'approved')->count(), 'suspended' => Organizer::where('status', 'suspended')->count(), 'rejected' => Organizer::where('status', 'rejected')->count()],
            'events' => $events,
            'tickets' => ['issued' => $issued, 'sold' => $sold, 'free' => $issued - $sold, 'used' => Ticket::where('status', 'used')->count(), 'cancelled' => Ticket::where('status', 'cancelled')->count()],
            'sales' => [
                'gross' => self::money(Order::where('status', 'confirmed')),
                'orders' => Order::where('status', 'confirmed')->count(),
                'fees_buyer' => self::money(Order::where('status', 'confirmed'), 'fees'),
            ],
            'commissions' => [
                'accrued' => self::money(CommissionEntry::where('status', 'accrued'), 'commission_amount'),
                'settled' => self::money(CommissionEntry::where('status', 'settled'), 'commission_amount'),
                'void' => self::money(CommissionEntry::where('status', 'void'), 'commission_amount'),
            ],
            'payments' => [
                'to_verify' => (clone $pay)->where('status', 'submitted')->count(),
                'to_verify_amount' => self::money((clone $pay)->where('status', 'submitted'), 'amount'),
                'to_verify_old' => (clone $pay)->where('status', 'submitted')->where('submitted_at', '<', $now->copy()->subDay())->count(),
                'late' => (clone $pay)->where('is_late', true)->count(),
                'suspicious' => (clone $pay)->where('is_suspicious', true)->whereIn('status', ['pending', 'submitted'])->count(),
                'refund_pending' => (clone $pay)->where('status', 'refund_pending')->count(),
                'refund_amount' => self::money((clone $pay)->where('status', 'refund_pending'), 'amount_received'),
                'incidents' => Order::where('status', 'incident')->count(),
            ],
            'activity' => self::activity(30),
            'top_events' => Event::query()->join('aero_tickets_tickets as t', 't.event_id', '=', 'aero_tickets_events.id')->whereIn('t.status', ['valid', 'used'])
                ->groupBy('aero_tickets_events.id', 'aero_tickets_events.name', 'aero_tickets_events.tenant_id')->orderByRaw('COUNT(*) DESC')->limit(5)
                ->get(['aero_tickets_events.id', 'aero_tickets_events.name', 'aero_tickets_events.tenant_id', DB::raw('COUNT(*) as tickets')])->all(),
            'top_organizers' => Order::where('status', 'confirmed')->groupBy('tenant_id')->orderByRaw('SUM(total) DESC')->limit(5)
                ->get(['tenant_id', DB::raw('SUM(total) as sales'), DB::raw('COUNT(*) as orders')])->all(),
            'overdue_commissions' => CommissionEntry::where('status', 'accrued')->groupBy('tenant_id')->orderByRaw('SUM(commission_amount) DESC')->limit(5)
                ->get(['tenant_id', DB::raw('SUM(commission_amount) as pending'), DB::raw('COUNT(*) as orders')])->all(),
        ];
    }

    /** Actividad diaria: órdenes creadas, entradas emitidas e ingresos en puerta. */
    public static function activity(int $days = 30): array
    {
        $from = now()->subDays($days - 1)->startOfDay();
        $by = function ($model, string $col, $where = null) use ($from) {
            $q = $model::query()->where($col, '>=', $from);
            $where && $where($q);

            return $q->selectRaw("DATE({$col}) d, COUNT(*) c")->groupBy('d')->pluck('c', 'd')->all();
        };
        $orders = $by(Order::class, 'created_at');
        $tickets = $by(Ticket::class, 'created_at', fn ($q) => $q->whereIn('status', ['valid', 'used']));
        $scans = $by(Scan::class, 'scanned_at', fn ($q) => $q->where('result', 'ok'));

        $out = [];
        for ($i = 0; $i < $days; $i++) {
            $d = $from->copy()->addDays($i)->toDateString();
            $out[] = ['date' => $d, 'orders' => (int) ($orders[$d] ?? 0), 'tickets' => (int) ($tickets[$d] ?? 0), 'scans' => (int) ($scans[$d] ?? 0)];
        }

        return $out;
    }
}
