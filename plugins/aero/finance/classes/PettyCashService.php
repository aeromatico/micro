<?php namespace Aero\Finance\Classes;

use Aero\Finance\Models\Account;
use Aero\Finance\Models\Movement;
use Aero\Finance\Models\PettyFund;
use Aero\Finance\Models\PettyOperation;
use Illuminate\Support\Facades\DB;

/**
 * Caja chica en bolivianos. Toda operación pasa por LedgerService, así el libro
 * nunca queda atrás:
 *   fondeo:     Dr caja chica · Cr Caja/Bancos
 *   devolución: Dr Caja/Bancos · Cr caja chica
 *   arqueo:     sobrante Dr caja chica · Cr Otros ingresos; faltante Dr Otros gastos · Cr caja chica
 *   gasto:      Movement de egreso pagado desde la cuenta de la caja chica
 */
class PettyCashService
{
    public function __construct(protected LedgerService $ledger, protected MovementService $movements)
    {
    }

    protected function assertUsable(PettyFund $f): void
    {
        if (!$f->is_active) {
            throw new FinanceException('La caja chica está desactivada.');
        }
    }

    protected function counterAccount(PettyFund $f, int $accountId): Account
    {
        $a = Account::where('tenant_id', $f->tenant_id)->find($accountId);
        if (!$a || $a->type !== 'asset' || $a->id === (int) $f->account_id) {
            throw new FinanceException('Elija una cuenta de Activo distinta de la caja chica (Caja o Bancos).');
        }

        return $a;
    }

    protected function amount($v): float
    {
        $v = round((float) $v, 2);
        if ($v <= 0) {
            throw new FinanceException('El monto debe ser mayor a cero.');
        }

        return $v;
    }

    protected function record(PettyFund $f, string $kind, $date, float $amount, float $diff, ?int $counterId, ?string $desc, ?int $entryId): PettyOperation
    {
        return PettyOperation::create([
            'tenant_id' => $f->tenant_id, 'fund_id' => $f->id, 'kind' => $kind, 'date' => $date, 'amount' => $amount,
            'difference' => $diff, 'counter_account_id' => $counterId, 'description' => $desc, 'status' => 'posted', 'entry_id' => $entryId,
        ]);
    }

    public function fund(PettyFund $f, $amount, int $fromAccountId, $date = null, ?string $desc = null): PettyOperation
    {
        $this->assertUsable($f);
        $amount = $this->amount($amount);
        $from = $this->counterAccount($f, $fromAccountId);
        $date = $date ?: today();

        return DB::transaction(function () use ($f, $amount, $from, $date, $desc) {
            $entry = $this->ledger->post((int) $f->tenant_id, $date, 'Fondeo de ' . $f->name . ($desc ? ' — ' . $desc : ''), [
                ['account_id' => $f->account_id, 'debit' => $amount],
                ['account_id' => $from->id, 'credit' => $amount],
            ]);

            return $this->record($f, 'fund', $date, $amount, 0, $from->id, $desc, $entry->id);
        });
    }

    public function giveBack(PettyFund $f, $amount, int $toAccountId, $date = null, ?string $desc = null): PettyOperation
    {
        $amount = $this->amount($amount);
        if ($amount > $f->balance + 0.004) {
            throw new FinanceException('La caja chica solo tiene Bs ' . number_format($f->balance, 2) . '.');
        }
        $to = $this->counterAccount($f, $toAccountId);
        $date = $date ?: today();

        return DB::transaction(function () use ($f, $amount, $to, $date, $desc) {
            $entry = $this->ledger->post((int) $f->tenant_id, $date, 'Devolución de ' . $f->name . ($desc ? ' — ' . $desc : ''), [
                ['account_id' => $to->id, 'debit' => $amount],
                ['account_id' => $f->account_id, 'credit' => $amount],
            ]);

            return $this->record($f, 'return', $date, $amount, 0, $to->id, $desc, $entry->id);
        });
    }

    /** Arqueo: compara el efectivo contado con el libro y asienta la diferencia. */
    public function count(PettyFund $f, $counted, $date = null, ?string $desc = null): PettyOperation
    {
        $counted = round((float) $counted, 2);
        if ($counted < 0) {
            throw new FinanceException('El efectivo contado no puede ser negativo.');
        }
        $date = $date ?: today();
        $tenantId = (int) $f->tenant_id;

        return DB::transaction(function () use ($f, $counted, $date, $desc, $tenantId) {
            $diff = round($counted - $f->balance, 2);
            $entryId = null;

            if (abs($diff) >= 0.01) {
                $abs = abs($diff);
                $other = AccountSeeder::system($tenantId, $diff > 0 ? 'other_income' : 'other_expense')->id;
                $lines = $diff > 0
                    ? [['account_id' => $f->account_id, 'debit' => $abs], ['account_id' => $other, 'credit' => $abs]]
                    : [['account_id' => $other, 'debit' => $abs], ['account_id' => $f->account_id, 'credit' => $abs]];
                $entryId = $this->ledger->post($tenantId, $date, ($diff > 0 ? 'Sobrante' : 'Faltante') . ' en arqueo de ' . $f->name, $lines)->id;
            }

            return $this->record($f, 'count', $date, $counted, $diff, null, $desc, $entryId);
        });
    }

    /** Gasto pagado desde la caja chica: queda como egreso normal (con IVA si se indica). */
    public function expense(PettyFund $f, array $data): Movement
    {
        $this->assertUsable($f);
        $amount = $this->amount($data['amount'] ?? 0);
        if ($amount > $f->balance + 0.004) {
            throw new FinanceException('La caja chica solo tiene Bs ' . number_format($f->balance, 2) . '; reponga el fondo.');
        }

        return $this->movements->record((int) $f->tenant_id, [
            'kind'                => 'expense',
            'date'                => $data['date'] ?? today()->toDateString(),
            'amount'              => $amount,
            'currency'            => 'BOB',
            'category_account_id' => (int) $data['category_account_id'],
            'cash_account_id'     => (int) $f->account_id,
            'description'         => $data['description'] ?? 'Gasto de caja chica',
            'counterparty'        => $data['counterparty'] ?? null,
            'document_no'         => $data['document_no'] ?? null,
            'tax_amount'          => (float) ($data['tax_amount'] ?? 0),
        ]);
    }

    public function void(PettyOperation $op, ?string $reason = null): void
    {
        if ($op->status === 'void' || $op->kind === 'count') {
            throw new FinanceException('Esta operación no se puede anular.');
        }
        if ($op->kind === 'fund' && $op->amount > $op->fund->balance + 0.004) {
            throw new FinanceException('No se puede anular el fondeo: ya se gastó parte de ese dinero.');
        }

        DB::transaction(function () use ($op, $reason) {
            $this->ledger->reverse($op->entry, $reason);
            $op->status = 'void';
            $op->save();
        });
    }
}
