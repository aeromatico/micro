<?php namespace Aero\Church\Classes;

use Aero\Church\Models\Contribution;

/**
 * Totales de aportes confirmados para el resumen. SOLO agregados: nunca nombres de aportantes,
 * para que el pastor vea las cifras sin ver quién dio qué.
 */
class GivingStats
{
    public function __construct(protected string $from, protected string $to, protected ?int $branchId = null, protected ?int $tenantId = null)
    {
    }

    /** Tenant explícito (servicios, tests) o el que el panel puede ver. */
    protected function scope()
    {
        return $this->tenantId ? Contribution::forTenant($this->tenantId) : Contribution::visible();
    }

    protected function base()
    {
        $q = $this->scope()->confirmed()->whereDate('date', '>=', $this->from)->whereDate('date', '<=', $this->to);

        return $this->branchId ? $q->where('branch_id', $this->branchId) : $q;
    }

    /** [moneda => total]. Las monedas no se mezclan. */
    public function totals(): array
    {
        return $this->base()->selectRaw('currency, sum(amount) as total, count(*) as n')->groupBy('currency')
            ->get()->mapWithKeys(fn ($r) => [$r->currency => ['total' => (float) $r->total, 'count' => (int) $r->n]])->all();
    }

    public function byFund(): array
    {
        return $this->base()->join('aero_church_funds as f', 'f.id', '=', 'aero_church_contributions.fund_id')
            ->selectRaw('f.name as label, aero_church_contributions.currency as currency, sum(aero_church_contributions.amount) as total')
            ->groupBy('f.name', 'aero_church_contributions.currency')->orderByDesc('total')->get()->all();
    }

    public function byBranch(): array
    {
        return $this->base()->leftJoin('aero_church_branches as b', 'b.id', '=', 'aero_church_contributions.branch_id')
            ->selectRaw("coalesce(b.name, 'Sin sede') as label, aero_church_contributions.currency as currency, sum(aero_church_contributions.amount) as total")
            ->groupBy('b.name', 'aero_church_contributions.currency')->orderByDesc('total')->get()->all();
    }

    public function byMethod(): array
    {
        return $this->base()->selectRaw('method as label, currency, sum(amount) as total')->groupBy('method', 'currency')->orderByDesc('total')->get()->all();
    }

    /** Evolución mensual de los últimos 12 meses (independiente del rango elegido). */
    public function monthly(): array
    {
        $from = now()->startOfMonth()->subMonths(11)->toDateString();
        $q = $this->scope()->confirmed()->where('date', '>=', $from);
        if ($this->branchId) {
            $q->where('branch_id', $this->branchId);
        }

        $rows = $q->get(['date', 'amount', 'currency'])->groupBy(fn ($c) => $c->date->format('Y-m'));
        $out = [];
        for ($i = 11; $i >= 0; $i--) {
            $key = now()->startOfMonth()->subMonths($i)->format('Y-m');
            $out[$key] = ($rows[$key] ?? collect())->groupBy('currency')->map(fn ($g) => (float) $g->sum('amount'))->all();
        }

        return $out;
    }
}
