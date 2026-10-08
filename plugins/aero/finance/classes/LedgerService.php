<?php namespace Aero\Finance\Classes;

use Aero\Finance\Models\Account;
use Aero\Finance\Models\FinanceSettings;
use Aero\Finance\Models\JournalEntry;
use Aero\Finance\Models\JournalLine;
use Illuminate\Support\Facades\DB;

/**
 * Libro diario. Única puerta para escribir asientos: valida el cuadre
 * (debe = haber), que las cuentas sean del tenant y que una misma fuente
 * (source_type/id/event) no se registre dos veces.
 */
class LedgerService
{
    /**
     * @param array<int,array{account_id:int,debit?:float,credit?:float,memo?:?string}> $lines
     * @param array{type:?string,id:?string,event:?string}|null $source
     */
    public function post(int $tenantId, $date, string $description, array $lines, ?array $source = null, ?int $reversalOf = null): JournalEntry
    {
        if (!FinanceSettings::isEnabled($tenantId)) {
            throw new FinanceException('Finanzas está desactivado. Actívelo en Configuración para registrar movimientos.');
        }

        if ($source && ($existing = $this->findBySource($tenantId, $source))) {
            return $existing; // idempotente
        }

        $lines = array_values(array_filter($lines, fn ($l) => round((float) ($l['debit'] ?? 0), 2) > 0 || round((float) ($l['credit'] ?? 0), 2) > 0));
        if (count($lines) < 2) {
            throw new FinanceException('Un asiento necesita al menos dos líneas.');
        }

        $debit = $credit = 0.0;
        foreach ($lines as $l) {
            $d = round((float) ($l['debit'] ?? 0), 2);
            $c = round((float) ($l['credit'] ?? 0), 2);
            if ($d < 0 || $c < 0 || ($d > 0 && $c > 0)) {
                throw new FinanceException('Cada línea va al debe o al haber, con importe positivo.');
            }
            $debit += $d;
            $credit += $c;
        }
        if (abs($debit - $credit) > 0.004) {
            throw new FinanceException(sprintf('El asiento no cuadra: debe %.2f, haber %.2f.', $debit, $credit));
        }

        $ids = array_unique(array_column($lines, 'account_id'));
        if (Account::where('tenant_id', $tenantId)->whereIn('id', $ids)->count() !== count($ids)) {
            throw new FinanceException('Alguna cuenta del asiento no pertenece a este negocio.');
        }

        return DB::transaction(function () use ($tenantId, $date, $description, $lines, $source, $reversalOf) {
            $number = (int) JournalEntry::where('tenant_id', $tenantId)->lockForUpdate()->max('number') + 1;

            $entry = JournalEntry::create([
                'tenant_id'      => $tenantId,
                'number'         => $number,
                'date'           => $date,
                'description'    => mb_substr($description, 0, 255),
                'status'         => 'posted',
                'reversal_of_id' => $reversalOf,
                'source_type'    => $source['type'] ?? null,
                'source_id'      => isset($source['id']) ? (string) $source['id'] : null,
                'source_event'   => $source['event'] ?? null,
            ]);

            foreach ($lines as $l) {
                JournalLine::create([
                    'tenant_id'  => $tenantId,
                    'entry_id'   => $entry->id,
                    'account_id' => $l['account_id'],
                    'debit'      => round((float) ($l['debit'] ?? 0), 2),
                    'credit'     => round((float) ($l['credit'] ?? 0), 2),
                    'memo'       => $l['memo'] ?? null,
                ]);
            }

            return $entry;
        });
    }

    public function findBySource(int $tenantId, array $source): ?JournalEntry
    {
        if (empty($source['type']) || !isset($source['id'])) {
            return null;
        }

        return JournalEntry::where('tenant_id', $tenantId)
            ->where('source_type', $source['type'])
            ->where('source_id', (string) $source['id'])
            ->where('source_event', $source['event'] ?? null)
            ->first();
    }

    /** Anula un asiento creando el inverso y marcando el original como void. */
    public function reverse(JournalEntry $entry, ?string $reason = null, ?array $source = null): JournalEntry
    {
        if ($entry->status !== 'posted') {
            throw new FinanceException('El asiento ya está anulado.');
        }
        if ($entry->reversal_of_id) {
            throw new FinanceException('Un asiento de anulación no se puede anular.');
        }

        return DB::transaction(function () use ($entry, $reason, $source) {
            $lines = $entry->lines->map(fn ($l) => [
                'account_id' => $l->account_id, 'debit' => (float) $l->credit, 'credit' => (float) $l->debit, 'memo' => $l->memo,
            ])->all();

            $reversal = $this->post(
                (int) $entry->tenant_id,
                today(),
                'Anulación del asiento #' . $entry->number . ($reason ? ' — ' . $reason : ''),
                $lines,
                $source,
                $entry->id
            );
            $entry->status = 'void';
            $entry->save();

            return $reversal;
        });
    }
}
