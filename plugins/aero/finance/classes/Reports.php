<?php namespace Aero\Finance\Classes;

use Aero\Finance\Models\Account;
use Aero\Finance\Models\JournalLine;
use Illuminate\Support\Facades\DB;

/** Reportes del libro. Recibe el tenant ya resuelto; null = superadmin (todo). */
class Reports
{
    public function __construct(protected ?int $tenantId)
    {
    }

    protected function lines()
    {
        $q = JournalLine::query()
            ->join('aero_finance_journal_entries as e', 'e.id', '=', 'aero_finance_journal_lines.entry_id')
            ->join('aero_finance_accounts as a', 'a.id', '=', 'aero_finance_journal_lines.account_id');

        return $this->tenantId ? $q->where('aero_finance_journal_lines.tenant_id', $this->tenantId) : $q;
    }

    /** Ingresos, egresos y resultado por mes (base de efectivo, anulaciones incluidas: se netean solas). */
    public function monthly($from, $to): array
    {
        $ym = DB::connection()->getDriverName() === 'sqlite' ? "strftime('%Y-%m', e.date)" : "DATE_FORMAT(e.date, '%Y-%m')";
        $rows = $this->lines()
            ->whereBetween('e.date', [$from->toDateString(), $to->toDateString()])
            ->whereIn('a.type', ['income', 'expense'])
            ->selectRaw("{$ym} as ym, a.type, SUM(aero_finance_journal_lines.credit - aero_finance_journal_lines.debit) as net")
            ->groupBy('ym', 'a.type')->get();

        $out = [];
        foreach ($rows as $r) {
            $out[$r->ym] ??= ['income' => 0.0, 'expense' => 0.0, 'result' => 0.0];
            if ($r->type === 'income') {
                $out[$r->ym]['income'] = round((float) $r->net, 2);
            } else {
                $out[$r->ym]['expense'] = round(-(float) $r->net, 2);
            }
        }
        foreach ($out as &$m) {
            $m['result'] = round($m['income'] - $m['expense'], 2);
        }
        ksort($out);

        return $out;
    }

    /** Ingresos y egresos del rango por cuenta (categoría). */
    public function byCategory($from, $to): array
    {
        return $this->lines()
            ->whereBetween('e.date', [$from->toDateString(), $to->toDateString()])
            ->whereIn('a.type', ['income', 'expense'])
            ->selectRaw('a.code, a.name, a.type, SUM(aero_finance_journal_lines.credit - aero_finance_journal_lines.debit) as net')
            ->groupBy('a.id', 'a.code', 'a.name', 'a.type')->orderBy('a.code')->get()
            ->map(fn ($r) => ['code' => $r->code, 'name' => $r->name, 'type' => $r->type, 'amount' => round($r->type === 'income' ? (float) $r->net : -(float) $r->net, 2)])
            ->all();
    }

    /** Libro mayor de una cuenta: movimientos del rango con saldo corrido (parte del saldo anterior). */
    public function ledger(int $accountId, $from, $to): array
    {
        $account = Account::query()->when($this->tenantId, fn ($q) => $q->where('tenant_id', $this->tenantId))->find($accountId);
        if (!$account) {
            return ['account' => null, 'opening' => 0.0, 'rows' => [], 'closing' => 0.0];
        }

        $sign = $account->isDebitNature() ? 1 : -1;
        $opening = (float) $this->lines()->where('aero_finance_journal_lines.account_id', $accountId)
            ->where('e.date', '<', $from->toDateString())
            ->selectRaw('COALESCE(SUM(aero_finance_journal_lines.debit - aero_finance_journal_lines.credit),0) as b')->value('b') * $sign;

        $rows = [];
        $balance = round($opening, 2);
        $list = $this->lines()->where('aero_finance_journal_lines.account_id', $accountId)
            ->whereBetween('e.date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('e.date')->orderBy('e.number')
            ->get(['e.date', 'e.number', 'e.description', 'e.status', 'aero_finance_journal_lines.debit', 'aero_finance_journal_lines.credit']);
        foreach ($list as $l) {
            $balance = round($balance + ((float) $l->debit - (float) $l->credit) * $sign, 2);
            $rows[] = ['date' => $l->date, 'number' => $l->number, 'description' => $l->description, 'status' => $l->status,
                'debit' => (float) $l->debit, 'credit' => (float) $l->credit, 'balance' => $balance];
        }

        return ['account' => $account, 'opening' => round($opening, 2), 'rows' => $rows, 'closing' => $balance];
    }

    /** Balance de sumas y saldos al fin del rango. Debe = Haber siempre. */
    public function trialBalance($to): array
    {
        $rows = $this->lines()->where('e.date', '<=', $to->toDateString())
            ->selectRaw('a.code, a.name, a.type, SUM(aero_finance_journal_lines.debit) as debit, SUM(aero_finance_journal_lines.credit) as credit')
            ->groupBy('a.id', 'a.code', 'a.name', 'a.type')->orderBy('a.code')->get();

        $out = [];
        $td = $tc = $sd = $sc = 0.0;
        foreach ($rows as $r) {
            $d = round((float) $r->debit, 2);
            $c = round((float) $r->credit, 2);
            $bal = round($d - $c, 2);
            $out[] = ['code' => $r->code, 'name' => $r->name, 'debit' => $d, 'credit' => $c,
                'debtor' => $bal > 0 ? $bal : 0.0, 'creditor' => $bal < 0 ? -$bal : 0.0];
            $td += $d;
            $tc += $c;
            $sd += max($bal, 0);
            $sc += max(-$bal, 0);
        }

        return ['rows' => $out, 'totals' => ['debit' => round($td, 2), 'credit' => round($tc, 2), 'debtor' => round($sd, 2), 'creditor' => round($sc, 2)]];
    }
}
