<?php namespace Aero\Realestate\Classes;

use Aero\Realestate\Models\Agent;
use Aero\Realestate\Models\Agreement;
use Aero\Realestate\Models\Commission;
use Aero\Realestate\Models\Contract;
use Aero\Realestate\Models\Document;
use Aero\Realestate\Models\Lead;
use Aero\Realestate\Models\Offer;
use Aero\Realestate\Models\Property;
use Aero\Realestate\Models\Visit;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Indicadores del panel y de los reportes. Todo sale de consultas ya filtradas por inmobiliaria: un usuario de
 * tenant solo ve lo suyo (y sin tenant resoluble no ve nada), el superadmin ve todo o el tenant que elija.
 * Los montos NUNCA se suman entre monedas: cada indicador devuelve un arreglo por moneda.
 */
class Stats
{
    public const PERIODS = [30 => 'Últimos 30 días', 90 => 'Últimos 90 días', 180 => 'Últimos 6 meses', 365 => 'Último año'];

    public Carbon $from;

    public function __construct(public ?int $tenantId, public int $days = 90, public bool $closed = false)
    {
        $this->days = isset(self::PERIODS[$days]) ? $days : 90;
        $this->from = now()->subDays($this->days)->startOfDay();
    }

    /** Alcance según quien mira el panel; `tenant` solo lo puede elegir el superadmin. */
    public static function forPanel(array $input): self
    {
        $days = (int) ($input['days'] ?? 90);
        if (CurrentTenant::isAdmin()) {
            $t = ($input['tenant'] ?? '') !== '' ? (int) $input['tenant'] : null;

            return new self($t, $days);
        }
        $id = CurrentTenant::id();

        return new self($id, $days, !$id);
    }

    protected function q(string $model): Builder
    {
        $q = $model::query();

        return $this->scope($q, $q->getModel()->getTable());
    }

    protected function scope($q, string $table)
    {
        if ($this->closed) {
            return $q->whereRaw('1 = 0');
        }

        return $this->tenantId ? $q->where($table . '.tenant_id', $this->tenantId) : $q;
    }

    // ───────────────────────── catálogo ─────────────────────────

    /** Propiedades publicadas con al menos una oferta disponible, por modalidad (una propiedad cuenta en cada modalidad que ofrece). */
    public function publishedByModality(): array
    {
        $rows = $this->scope(Offer::query()->join('aero_realestate_properties as p', 'p.id', '=', 'aero_realestate_offers.property_id')
            ->whereNull('p.deleted_at')->where('p.is_published', true)->where('p.status', '!=', 'inactive')
            ->where('aero_realestate_offers.status', 'available'), 'aero_realestate_offers')
            ->selectRaw('aero_realestate_offers.modality as k, count(distinct aero_realestate_offers.property_id) as n')->groupBy('aero_realestate_offers.modality')->pluck('n', 'k')->all();

        return ['sale' => (int) ($rows['sale'] ?? 0), 'rent' => (int) ($rows['rent'] ?? 0), 'anticretico' => (int) ($rows['anticretico'] ?? 0)];
    }

    /** Disponibles, reservadas, con operación cerrada (vendida / alquilada / en anticrético) e inactivas. */
    public function statusBreakdown(): array
    {
        $rows = $this->q(Property::class)->selectRaw('status as k, count(*) as n')->groupBy('status')->pluck('n', 'k')->all();
        $n = fn ($k) => (int) ($rows[$k] ?? 0);

        return [
            'available' => $n('available'), 'reserved' => $n('reserved'), 'closed' => $n('sold') + $n('rented') + $n('anticretico'),
            'sold' => $n('sold'), 'rented' => $n('rented'), 'anticretico' => $n('anticretico'), 'inactive' => $n('inactive'), 'total' => array_sum($rows),
            'unpublished' => $this->q(Property::class)->where('is_published', false)->where('status', '!=', 'inactive')->count(),
        ];
    }

    /** Más vistas del portal (acumulado) con las visitas agendadas en el periodo. */
    public function topViewed(int $limit = 5): array
    {
        $props = $this->q(Property::class)->where('is_published', true)->where('views_count', '>', 0)->orderByDesc('views_count')->limit($limit)->get(['id', 'code', 'title', 'city', 'zone', 'views_count']);
        $visits = $this->q(Visit::class)->whereIn('property_id', $props->pluck('id'))->where('starts_at', '>=', $this->from)->where('status', '!=', 'cancelled')
            ->selectRaw('property_id as k, count(*) as n')->groupBy('property_id')->pluck('n', 'k');

        return $props->map(fn ($p) => ['id' => $p->id, 'title' => $p->title, 'code' => $p->code, 'where' => implode(', ', array_filter([$p->zone, $p->city])), 'views' => (int) $p->views_count, 'visits' => (int) ($visits[$p->id] ?? 0)])->all();
    }

    /** Días promedio entre publicar y cerrar la operación (operaciones cerradas en el periodo) y antigüedad de lo aún disponible. */
    public function daysOnMarket(): array
    {
        $closed = $this->q(Lead::class)->where('stage', 'closed_won')->where('closed_at', '>=', $this->from)
            ->join('aero_realestate_properties as p', 'p.id', '=', 'aero_realestate_leads.property_id')->whereNotNull('p.published_at')
            ->get(['aero_realestate_leads.closed_at', 'p.published_at']);
        $days = $closed->map(fn ($r) => max(0, Carbon::parse($r->published_at)->diffInDays(Carbon::parse($r->closed_at), true)));

        $live = $this->q(Property::class)->where('is_published', true)->where('status', 'available')->whereNotNull('published_at')->pluck('published_at');
        $age = $live->map(fn ($d) => Carbon::parse($d)->diffInDays(now(), true));

        return [
            'closed_avg' => $days->isEmpty() ? null : (int) round($days->avg()), 'closed_n' => $days->count(),
            'live_avg'   => $age->isEmpty() ? null : (int) round($age->avg()), 'live_n' => $age->count(),
        ];
    }

    // ───────────────────────── comercial ─────────────────────────

    /** De los interesados que entraron en el periodo, cuántos se volvieron clientes (operación cerrada). */
    public function conversion(): array
    {
        $by = $this->q(Lead::class)->where('created_at', '>=', $this->from)->selectRaw('stage as k, count(*) as n')->groupBy('stage')->pluck('n', 'k')->all();
        $total = array_sum($by);
        $won = (int) ($by['closed_won'] ?? 0);

        return [
            'total' => $total, 'won' => $won, 'lost' => (int) ($by['closed_lost'] ?? 0), 'open' => $total - $won - (int) ($by['closed_lost'] ?? 0),
            'rate' => $total ? round($won / $total * 100, 1) : null, 'by_stage' => $by,
            'new_clients' => $this->q(\Aero\Realestate\Models\Client::class)->where('created_at', '>=', $this->from)->count(),
        ];
    }

    /** Visitas del periodo (desde su inicio hasta hoy) por estado, más las próximas. */
    public function visits(): array
    {
        $by = $this->q(Visit::class)->where('starts_at', '>=', $this->from)->where('starts_at', '<=', now())->selectRaw('status as k, count(*) as n')->groupBy('status')->pluck('n', 'k')->all();
        $upcoming = $this->q(Visit::class)->upcoming()->count();

        return [
            'done' => (int) ($by['done'] ?? 0), 'cancelled' => (int) ($by['cancelled'] ?? 0),
            'unattended' => (int) (($by['confirmed'] ?? 0) + ($by['requested'] ?? 0) + ($by['rescheduled'] ?? 0)),   // pasaron sin marcarse realizadas
            'scheduled' => array_sum($by) + $upcoming - (int) ($by['cancelled'] ?? 0), 'upcoming' => $upcoming,
            'requested' => $this->q(Visit::class)->where('status', 'requested')->where('starts_at', '>=', now())->count(),
        ];
    }

    /** Comisiones del periodo por moneda y estado (comisión de la inmobiliaria y parte de los agentes). */
    public function commissions(): array
    {
        $rows = $this->q(Commission::class)->where('created_at', '>=', $this->from)->where('status', '!=', 'cancelled')
            ->selectRaw('currency, status, sum(commission_amount) as total, sum(agent_amount) as agents, count(*) as n')->groupBy('currency', 'status')->get();
        $out = [];
        foreach ($rows as $r) {
            $c = &$out[$r->currency];
            $c ??= ['total' => 0.0, 'agents' => 0.0, 'pending' => 0.0, 'paid' => 0.0, 'n' => 0];
            $c['total'] += (float) $r->total;
            $c['agents'] += (float) $r->agents;
            $c['n'] += (int) $r->n;
            $c[$r->status === 'paid' ? 'paid' : 'pending'] += (float) $r->total;
            unset($c);
        }

        return $out;
    }

    /** Operaciones cerradas y comisiones por agente en el periodo. */
    public function byAgent(): array
    {
        $agents = $this->q(Agent::class)->orderBy('name')->get(['id', 'name', 'is_active']);
        $leads = $this->q(Lead::class)->where('created_at', '>=', $this->from)->get(['id', 'agent_id', 'stage', 'created_at', 'closed_at']);
        $closed = $this->q(Lead::class)->where('stage', 'closed_won')->where('closed_at', '>=', $this->from)->get(['id', 'agent_id', 'created_at', 'closed_at', 'modality']);
        $visits = $this->q(Visit::class)->where('starts_at', '>=', $this->from)->where('starts_at', '<=', now())->where('status', 'done')->pluck('agent_id');
        $comm = $this->q(Commission::class)->where('created_at', '>=', $this->from)->where('status', '!=', 'cancelled')->get(['agent_id', 'currency', 'commission_amount', 'agent_amount', 'status']);

        $rows = [];
        foreach ($agents->push((object) ['id' => null, 'name' => 'Sin agente', 'is_active' => true]) as $a) {
            $mine = $leads->where('agent_id', $a->id);
            $won = $closed->where('agent_id', $a->id);
            $money = [];
            foreach ($comm->where('agent_id', $a->id) as $c) {
                $money[$c->currency]['agency'] = ($money[$c->currency]['agency'] ?? 0) + (float) $c->commission_amount;
                $money[$c->currency]['agent'] = ($money[$c->currency]['agent'] ?? 0) + (float) $c->agent_amount;
            }
            $row = [
                'id' => $a->id, 'name' => $a->name, 'active' => (bool) $a->is_active, 'leads' => $mine->count(), 'contacted' => $mine->where('stage', '!=', 'new')->count(),
                'visits' => $visits->filter(fn ($v) => $v === $a->id)->count(), 'won' => $won->count(),
                'by_modality' => ['sale' => $won->where('modality', 'sale')->count(), 'rent' => $won->where('modality', 'rent')->count(), 'anticretico' => $won->where('modality', 'anticretico')->count()],
                'rate' => $mine->count() ? round($mine->where('stage', 'closed_won')->count() / $mine->count() * 100, 1) : null,
                'avg_days' => $won->isEmpty() ? null : (int) round($won->map(fn ($l) => Carbon::parse($l->created_at)->diffInDays(Carbon::parse($l->closed_at), true))->avg()),
                'money' => $money,
            ];
            if ($a->id !== null || $row['leads'] || $row['won']) {
                $rows[] = $row;
            }
        }
        usort($rows, fn ($x, $y) => [$y['won'], $y['leads']] <=> [$x['won'], $x['leads']]);

        return $rows;
    }

    /** Demanda (oportunidades del periodo) por ciudad, zona y tipo de propiedad. */
    public function demand(): array
    {
        $base = fn () => $this->q(Lead::class)->where('aero_realestate_leads.created_at', '>=', $this->from)
            ->join('aero_realestate_properties as p', 'p.id', '=', 'aero_realestate_leads.property_id');
        $by = function (string $col, int $limit) use ($base) {
            return $base()->whereNotNull("p.$col")->selectRaw("p.$col as k, count(*) as n, sum(case when aero_realestate_leads.stage = 'closed_won' then 1 else 0 end) as won")
                ->groupBy("p.$col")->orderByDesc('n')->limit($limit)->get()->map(fn ($r) => ['k' => $r->k, 'n' => (int) $r->n, 'won' => (int) $r->won])->all();
        };
        $types = array_map(fn ($r) => ['k' => Property::$types[$r['k']] ?? $r['k']] + $r, $by('type', 10));

        return ['city' => $by('city', 8), 'zone' => $by('zone', 10), 'type' => $types];
    }

    // ───────────────────────── anticréticos y vencimientos ─────────────────────────

    /** Capital de los anticréticos vigentes por moneda: pactado y por devolver. */
    public function anticreticoCapital(): array
    {
        $rows = $this->q(Contract::class)->where('status', 'active')->selectRaw('currency, count(*) as n, sum(capital) as capital, sum(delivered_amount - returned_amount) as pending')->groupBy('currency')->get();
        $out = [];
        foreach ($rows as $r) {
            $out[$r->currency] = ['n' => (int) $r->n, 'capital' => (float) $r->capital, 'pending' => (float) $r->pending];
        }

        return $out;
    }

    public function contractStatuses(): array
    {
        $c = $this->q(Contract::class)->get(['status', 'end_date', 'in_dispute', 'return_status']);
        $active = $c->where('status', 'active');

        return [
            'active' => $active->count(),
            'expiring' => $active->filter(fn ($x) => $x->days_left !== null && $x->days_left >= 0 && $x->days_left <= Contract::EXPIRING_DAYS)->count(),
            'overdue' => $active->filter(fn ($x) => $x->days_left !== null && $x->days_left < 0)->count(),
            'dispute' => $c->where('in_dispute', true)->count(),
            'finished' => $c->where('status', 'finished')->count(),
            'open' => $c->whereIn('status', ['draft', 'reserved'])->count(),
        ];
    }

    /** Anticréticos y alquileres que vencen en los próximos días (y los ya vencidos), del más urgente al menos. */
    public function expiring(int $days = Contract::EXPIRING_DAYS, int $limit = 15): array
    {
        $items = [];
        foreach ($this->q(Contract::class)->expiring($days)->with(['property', 'client'])->get() as $c) {
            $items[] = ['kind' => 'Anticrético', 'code' => $c->code, 'title' => $c->property?->title, 'party' => $c->client?->name, 'end' => $c->end_date, 'left' => $c->days_left,
                'amount' => number_format((float) $c->capital, 0, ',', '.') . ' ' . $c->currency, 'url' => 'aero/realestate/contracts/update/' . $c->id];
        }
        foreach ($this->q(Agreement::class)->expiring($days)->with(['property', 'client'])->get() as $a) {
            $items[] = ['kind' => 'Alquiler', 'code' => $a->code, 'title' => $a->property?->title, 'party' => $a->client?->name, 'end' => $a->end_date, 'left' => $a->days_left,
                'amount' => number_format((float) $a->amount, 0, ',', '.') . ' ' . $a->currency . '/mes', 'url' => 'aero/realestate/agreements/update/' . $a->id];
        }
        usort($items, fn ($x, $y) => $x['left'] <=> $y['left']);

        return array_slice($items, 0, $limit);
    }

    /** Lo que pide atención hoy. */
    public function attention(): array
    {
        return [
            'visits_requested' => $this->q(Visit::class)->where('status', 'requested')->where('starts_at', '>=', now())->count(),
            'follow_ups' => $this->q(Lead::class)->open()->whereNotNull('next_follow_up_at')->where('next_follow_up_at', '<=', now())->count(),
            'documents_pending' => $this->q(Document::class)->where('status', 'pending')->count(),
            'agreements_review' => $this->q(Agreement::class)->where('status', 'in_review')->count(),
            'unassigned_leads' => $this->q(Lead::class)->open()->whereNull('agent_id')->count(),
            'commissions_pending' => $this->q(Commission::class)->where('status', 'pending')->count(),
        ];
    }

    /** Serie de los últimos N meses: oportunidades nuevas, operaciones cerradas y visitas realizadas. */
    public function monthly(int $months = 6): array
    {
        $start = now()->startOfMonth()->subMonths($months - 1);
        $labels = [];
        for ($i = 0; $i < $months; $i++) {
            $labels[$start->copy()->addMonths($i)->format('Y-m')] = $start->copy()->addMonths($i)->locale('es')->translatedFormat('M');
        }
        $zero = array_fill_keys(array_keys($labels), 0);
        $count = function ($dates) use ($zero) {
            $out = $zero;
            foreach ($dates as $d) {
                $k = Carbon::parse($d)->format('Y-m');
                isset($out[$k]) && $out[$k]++;
            }

            return $out;
        };

        return [
            'labels' => array_values($labels),
            'leads' => array_values($count($this->q(Lead::class)->where('created_at', '>=', $start)->pluck('created_at'))),
            'deals' => array_values($count($this->q(Lead::class)->where('stage', 'closed_won')->where('closed_at', '>=', $start)->pluck('closed_at'))),
            'visits' => array_values($count($this->q(Visit::class)->where('status', 'done')->where('starts_at', '>=', $start)->where('starts_at', '<=', now())->pluck('starts_at'))),
        ];
    }

    /** Todo junto para el panel. */
    public function dashboard(): array
    {
        return [
            'modality' => $this->publishedByModality(), 'status' => $this->statusBreakdown(), 'top' => $this->topViewed(), 'market' => $this->daysOnMarket(),
            'conversion' => $this->conversion(), 'visits' => $this->visits(), 'commissions' => $this->commissions(), 'capital' => $this->anticreticoCapital(),
            'contracts' => $this->contractStatuses(), 'expiring' => $this->expiring(), 'demand' => $this->demand(), 'monthly' => $this->monthly(), 'attention' => $this->attention(),
        ];
    }
}
