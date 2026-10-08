<?php namespace Aero\Finance\Classes;

use Aero\Finance\Models\Account;
use Aero\Finance\Models\Movement;

/**
 * Ingresos y egresos. Cada movimiento genera su asiento de partida doble:
 *   ingreso: Dr cuenta de cobro · Cr categoría (+ Cr IVA débito)
 *   egreso:  Dr categoría (+ Dr IVA crédito) · Cr cuenta de pago
 * Los importes del libro siempre van en bolivianos.
 */
class MovementService
{
    public function __construct(protected LedgerService $ledger)
    {
    }

    /** Tipo de cambio a BOB. Null en el parámetro = el del día (USD) o 1 (BOB). */
    public static function rateFor(string $currency, ?float $rate = null): float
    {
        $currency = strtoupper($currency);
        if ($currency === 'BOB') {
            return 1.0;
        }
        if ($rate && $rate > 0) {
            return $rate;
        }
        if ($currency === 'USD') {
            $r = class_exists(\Aero\Sites\Models\Settings::class) ? \Aero\Sites\Models\Settings::getUsdToBobRate() : null;

            return $r && $r > 0 ? (float) $r : 6.96;
        }

        throw new FinanceException("Indique el tipo de cambio de {$currency} a BOB.");
    }

    /** Crea el movimiento (su asiento se genera en Movement::afterCreate → postEntry). */
    public function record(int $tenantId, array $data, ?array $source = null): Movement
    {
        AccountSeeder::ensure($tenantId);

        $data['currency'] = strtoupper($data['currency'] ?? 'BOB');
        $data['exchange_rate'] = self::rateFor($data['currency'], isset($data['exchange_rate']) ? (float) $data['exchange_rate'] : null);
        $data['tenant_id'] = $tenantId;

        $m = new Movement($data);
        $m->source_type = $source['type'] ?? null;
        $m->source_id = isset($source['id']) ? (string) $source['id'] : null;
        $m->save();

        return $m->fresh();
    }

    public function postEntry(Movement $m): void
    {
        $tenantId = (int) $m->tenant_id;
        $rate = (float) ($m->exchange_rate ?: 1);
        $base = round((float) $m->amount * $rate, 2);
        $tax = min($base, round((float) $m->tax_amount * $rate, 2));
        $net = round($base - $tax, 2);

        $accounts = Account::where('tenant_id', $tenantId)->whereIn('id', [$m->category_account_id, $m->cash_account_id])->get()->keyBy('id');
        $category = $accounts[$m->category_account_id] ?? null;
        $cash = $accounts[$m->cash_account_id] ?? null;
        if (!$category || !$cash) {
            throw new FinanceException('Categoría o cuenta de cobro/pago inválida para este negocio.');
        }
        if ($m->kind === 'income' && $category->type !== 'income') {
            throw new FinanceException('Un ingreso debe usar una categoría de tipo Ingreso.');
        }
        if ($m->kind === 'expense' && $category->type !== 'expense') {
            throw new FinanceException('Un egreso debe usar una categoría de tipo Egreso.');
        }
        if ($cash->type !== 'asset') {
            throw new FinanceException('La cuenta de cobro/pago debe ser de Activo (Caja, Bancos…).');
        }

        $memo = $m->description;
        if ($m->kind === 'income') {
            $lines = [
                ['account_id' => $cash->id, 'debit' => $base, 'memo' => $memo],
                ['account_id' => $category->id, 'credit' => $net, 'memo' => $memo],
            ];
            if ($tax > 0) {
                $lines[] = ['account_id' => AccountSeeder::system($tenantId, 'vat_debit')->id, 'credit' => $tax, 'memo' => 'IVA'];
            }
        } else {
            $lines = [
                ['account_id' => $category->id, 'debit' => $net, 'memo' => $memo],
                ['account_id' => $cash->id, 'credit' => $base, 'memo' => $memo],
            ];
            if ($tax > 0) {
                $lines[] = ['account_id' => AccountSeeder::system($tenantId, 'vat_credit')->id, 'debit' => $tax, 'memo' => 'IVA'];
            }
        }

        $source = $m->source_type ? ['type' => $m->source_type, 'id' => $m->source_id, 'event' => 'movement'] : null;
        $entry = $this->ledger->post($tenantId, $m->date, ($m->kind === 'income' ? 'Ingreso: ' : 'Egreso: ') . $m->description, $lines, $source);

        // Escritura directa: el modelo es inmutable y aún está en su afterCreate.
        Movement::query()->whereKey($m->id)->toBase()->update(['entry_id' => $entry->id]);
        $m->setAttribute('entry_id', $entry->id);
        $m->syncOriginalAttribute('entry_id');
    }

    public function void(Movement $m, ?string $reason = null): Movement
    {
        if ($m->status === 'void') {
            throw new FinanceException('El movimiento ya está anulado.');
        }

        if ($m->entry) {
            $this->ledger->reverse($m->entry, $reason);
        }
        $m->status = 'void';
        $m->save();

        return $m;
    }
}
