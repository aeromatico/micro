<?php namespace Aero\Crm\Classes;

use Illuminate\Support\Facades\DB;

/**
 * Informes del CRM de UN tenant para el agente: las cifras las calcula este
 * código (no el modelo), siempre acotadas por tenant_id.
 */
class AgentReports
{
    public static function build(string $report, int $tenantId, int $days): array
    {
        return match ($report) {
            'sales_funnel'      => static::salesFunnel($tenantId, $days),
            'lead_sources'      => static::leadSources($tenantId, $days),
            'activity_load'     => static::activityLoad($tenantId, $days),
            'collections_aging' => static::collectionsAging($tenantId, $days),
            'contacts'          => static::contacts($tenantId, $days),
            'stalled_deals'     => static::stalledDeals($tenantId, $days),
            'tickets'           => static::tickets($tenantId, $days),
            default             => ['error' => 'Informe no válido.'],
        };
    }

    protected static function since(int $days): string
    {
        return now()->subDays($days)->toDateTimeString();
    }

    protected static function pct(int $part, int $whole): ?float
    {
        return $whole > 0 ? round($part * 100 / $whole, 1) : null;
    }

    protected static function salesFunnel(int $t, int $days): array
    {
        $since = static::since($days);
        $deals = fn () => DB::table('aero_crm_deals')->where('tenant_id', $t);

        $byStage = DB::table('aero_crm_pipeline_stages as s')
            ->leftJoin('aero_crm_deals as d', fn ($j) => $j->on('d.stage_id', '=', 's.id')->where('d.tenant_id', '=', $t))
            ->where('s.tenant_id', $t)->groupBy('s.id', 's.name', 's.sort_order', 's.is_won', 's.is_lost')->orderBy('s.sort_order')
            ->selectRaw('s.id, s.name, s.is_won, s.is_lost, count(d.id) as deals, coalesce(sum(d.value),0) as value')->get()
            ->map(fn ($r) => ['stage_id' => (int) $r->id, 'stage' => $r->name, 'kind' => $r->is_won ? 'won' : ($r->is_lost ? 'lost' : 'open'), 'deals' => (int) $r->deals, 'value' => (float) $r->value])->all();

        $won = $deals()->where('status', 'won')->where('closed_at', '>=', $since);
        $lost = $deals()->where('status', 'lost')->where('closed_at', '>=', $since);
        $wonN = (int) (clone $won)->count();
        $lostN = (int) (clone $lost)->count();

        $cycle = $deals()->where('status', 'won')->where('closed_at', '>=', $since)->get(['created_at', 'closed_at'])
            ->map(fn ($d) => \Illuminate\Support\Carbon::parse($d->created_at)->diffInDays(\Illuminate\Support\Carbon::parse($d->closed_at), true));

        return [
            'period_days'   => $days,
            'by_stage'      => $byStage,
            'open'          => ['deals' => (int) $deals()->where('status', 'open')->count(), 'value_by_currency' => static::sumByCurrency($deals()->where('status', 'open'))],
            'won_in_period'  => ['deals' => $wonN, 'value_by_currency' => static::sumByCurrency($won)],
            'lost_in_period' => ['deals' => $lostN, 'value_by_currency' => static::sumByCurrency($lost)],
            'win_rate_pct'  => static::pct($wonN, $wonN + $lostN),
            'avg_cycle_days' => $cycle->isEmpty() ? null : round($cycle->avg(), 1),
            'new_in_period' => (int) $deals()->where('created_at', '>=', $since)->count(),
            'note' => 'Los valores se muestran por moneda: no se suman monedas distintas.',
        ];
    }

    protected static function sumByCurrency($query): array
    {
        return $query->selectRaw("coalesce(nullif(currency, ''), 'S/M') as cur, sum(value) as v")->groupBy('cur')->pluck('v', 'cur')->map(fn ($v) => (float) $v)->all();
    }

    protected static function leadSources(int $t, int $days): array
    {
        $since = static::since($days);
        $rows = DB::table('aero_crm_leads')->where('tenant_id', $t)->where('created_at', '>=', $since)
            ->selectRaw("coalesce(nullif(source,''), 'Sin origen') as src, count(*) as total, sum(case when converted_deal_id is not null then 1 else 0 end) as converted, sum(case when status='qualified' then 1 else 0 end) as qualified, sum(case when status='disqualified' then 1 else 0 end) as disqualified")
            ->groupBy('src')->orderByDesc('total')->get();

        $total = (int) $rows->sum('total');

        return [
            'period_days' => $days,
            'total_leads' => $total,
            'by_source'   => $rows->map(fn ($r) => ['source' => $r->src, 'leads' => (int) $r->total, 'qualified' => (int) $r->qualified, 'disqualified' => (int) $r->disqualified, 'converted' => (int) $r->converted,
                'conversion_pct' => static::pct((int) $r->converted, (int) $r->total)])->all(),
            'by_status'   => DB::table('aero_crm_leads')->where('tenant_id', $t)->where('created_at', '>=', $since)->selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status')->all(),
            'conversion_pct' => static::pct((int) $rows->sum('converted'), $total),
        ];
    }

    protected static function activityLoad(int $t, int $days): array
    {
        $since = static::since($days);
        $open = fn () => DB::table('aero_crm_activities')->where('tenant_id', $t)->whereIn('status', ['pending', 'in_progress']);

        return [
            'period_days'   => $days,
            'open'          => (int) $open()->count(),
            'overdue'       => (int) $open()->where('due_at', '<', now())->count(),
            'due_next_7_days' => (int) $open()->whereBetween('due_at', [now(), now()->addDays(7)])->count(),
            'completed_in_period' => (int) DB::table('aero_crm_activities')->where('tenant_id', $t)->where('status', 'completed')->where('completed_at', '>=', $since)->count(),
            'by_type_in_period' => DB::table('aero_crm_activities')->where('tenant_id', $t)->where('created_at', '>=', $since)->selectRaw('type, count(*) n')->groupBy('type')->pluck('n', 'type')->all(),
            'overdue_oldest' => $open()->where('due_at', '<', now())->orderBy('due_at')->limit(10)->get(['id', 'type', 'subject', 'due_at'])
                ->map(fn ($a) => ['id' => (int) $a->id, 'type' => $a->type, 'subject' => $a->subject, 'due_at' => (string) $a->due_at])->all(),
        ];
    }

    protected static function collectionsAging(int $t, int $days): array
    {
        $pending = DB::table('aero_crm_collection_items')->where('tenant_id', $t)->where('status', 'pending')->get(['id', 'concept', 'amount', 'currency', 'due_date']);
        $today = now()->startOfDay();
        $buckets = ['not_due' => [], '1_30' => [], '31_60' => [], '61_90' => [], 'over_90' => []];

        foreach ($pending as $c) {
            $late = $c->due_date ? \Illuminate\Support\Carbon::parse($c->due_date)->startOfDay()->diffInDays($today, false) : 0;
            $key = $late <= 0 ? 'not_due' : ($late <= 30 ? '1_30' : ($late <= 60 ? '31_60' : ($late <= 90 ? '61_90' : 'over_90')));
            $cur = $c->currency ?: 'S/M';
            $buckets[$key][$cur] = ($buckets[$key][$cur] ?? ['items' => 0, 'amount' => 0.0]);
            $buckets[$key][$cur]['items']++;
            $buckets[$key][$cur]['amount'] = round($buckets[$key][$cur]['amount'] + (float) $c->amount, 2);
        }

        $paid = DB::table('aero_crm_collection_items')->where('tenant_id', $t)->where('status', 'paid')->where('paid_at', '>=', static::since($days));

        return [
            'period_days' => $days,
            'pending_total' => (int) $pending->count(),
            'aging' => $buckets,
            'paid_in_period' => ['items' => (int) (clone $paid)->count(), 'amount_by_currency' => (clone $paid)->selectRaw("coalesce(nullif(currency,''),'S/M') c, sum(amount) v")->groupBy('c')->pluck('v', 'c')->map(fn ($v) => (float) $v)->all()],
            'most_overdue' => DB::table('aero_crm_collection_items')->where('tenant_id', $t)->where('status', 'pending')->where('due_date', '<', now()->toDateString())
                ->orderBy('due_date')->limit(10)->get(['id', 'concept', 'amount', 'currency', 'due_date', 'reminder_count'])
                ->map(fn ($c) => ['id' => (int) $c->id, 'concept' => $c->concept, 'amount' => (float) $c->amount, 'currency' => $c->currency, 'due_date' => (string) $c->due_date, 'reminders_sent' => (int) $c->reminder_count])->all(),
        ];
    }

    protected static function contacts(int $t, int $days): array
    {
        $c = fn () => DB::table('aero_crm_contacts')->where('tenant_id', $t);
        $dupes = fn (string $col) => $c()->whereNotNull($col)->where($col, '!=', '')->select($col)->groupBy($col)->havingRaw('count(*) > 1')->get()->count();

        return [
            'period_days' => $days,
            'total'       => (int) $c()->count(),
            'new_in_period' => (int) $c()->where('created_at', '>=', static::since($days))->count(),
            'by_source'   => $c()->selectRaw("coalesce(nullif(source,''), 'Sin origen') as s, count(*) n")->groupBy('s')->orderByDesc('n')->limit(10)->pluck('n', 's')->all(),
            'data_quality' => [
                'without_email' => (int) $c()->where(fn ($q) => $q->whereNull('email')->orWhere('email', ''))->count(),
                'without_phone' => (int) $c()->where(fn ($q) => $q->whereNull('phone')->orWhere('phone', ''))->count(),
                'without_name'  => (int) $c()->where(fn ($q) => $q->whereNull('first_name')->orWhere('first_name', ''))->where(fn ($q) => $q->whereNull('last_name')->orWhere('last_name', ''))->count(),
                'without_company' => (int) $c()->whereNull('company_id')->count(),
                'duplicate_emails' => (int) $dupes('email'),
                'duplicate_phones' => (int) $dupes('phone'),
            ],
        ];
    }

    protected static function stalledDeals(int $t, int $days): array
    {
        $limit = now()->subDays($days);
        $deals = DB::table('aero_crm_deals')->where('tenant_id', $t)->where('status', 'open')->where('updated_at', '<', $limit)->orderBy('updated_at')->limit(50)->get();
        $out = [];

        foreach ($deals as $d) {
            $last = DB::table('aero_crm_activities')->where('tenant_id', $t)->where('related_type', \Aero\Crm\Models\Deal::class)->where('related_id', $d->id)->max('created_at');

            if ($last && \Illuminate\Support\Carbon::parse($last)->gte($limit)) {
                continue;
            }

            $out[] = ['id' => (int) $d->id, 'title' => $d->title, 'stage_id' => (int) $d->stage_id, 'value' => (float) $d->value, 'currency' => $d->currency,
                'days_without_movement' => (int) \Illuminate\Support\Carbon::parse($d->updated_at)->diffInDays(now(), true), 'last_activity' => $last ? (string) $last : null];
        }

        return ['stalled_after_days' => $days, 'count' => count($out), 'deals' => array_slice($out, 0, 20)];
    }

    protected static function tickets(int $t, int $days): array
    {
        $since = static::since($days);
        $q = fn () => DB::table('aero_crm_tickets')->where('tenant_id', $t);
        $answered = $q()->where('created_at', '>=', $since)->whereNotNull('first_response_at')->get(['created_at', 'first_response_at'])
            ->map(fn ($r) => \Illuminate\Support\Carbon::parse($r->created_at)->diffInMinutes(\Illuminate\Support\Carbon::parse($r->first_response_at), true) / 60);

        return [
            'period_days' => $days,
            'created_in_period' => (int) $q()->where('created_at', '>=', $since)->count(),
            'by_status' => $q()->selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status')->all(),
            'open_by_priority' => $q()->whereIn('status', ['open', 'pending'])->selectRaw('priority, count(*) n')->groupBy('priority')->pluck('n', 'priority')->all(),
            'open_older_than_7_days' => (int) $q()->whereIn('status', ['open', 'pending'])->where('created_at', '<', now()->subDays(7))->count(),
            'avg_first_response_hours' => $answered->isEmpty() ? null : round($answered->avg(), 1),
            'by_source' => $q()->where('created_at', '>=', $since)->selectRaw('source, count(*) n')->groupBy('source')->pluck('n', 'source')->all(),
        ];
    }
}
