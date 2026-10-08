<?php namespace Aero\Pos\Classes;

use Aero\Pos\Models\CashMovement;
use Aero\Pos\Models\Payment;
use Aero\Pos\Models\Shift;
use Aero\Pos\Models\Terminal;
use Aero\Shop\Classes\Exceptions\OrderException;
use Db;

/**
 * Turnos de caja: abrir con monto inicial, registrar ingresos/egresos y cerrar con
 * arqueo. Efectivo esperado = inicial + cobros en efectivo (sin vuelto) + ingresos − egresos.
 */
class ShiftService
{
    public function open(int $tenantId, int $terminalId, int $userId, float $openingCash = 0): Shift
    {
        $terminal = Terminal::forTenant($tenantId)->where('is_active', true)->find($terminalId);
        if (!$terminal) {
            throw new OrderException('La terminal no existe o está desactivada.');
        }
        if ($openingCash < 0) {
            throw new OrderException('El monto inicial no puede ser negativo.');
        }

        return Db::transaction(function () use ($tenantId, $terminal, $userId, $openingCash) {
            // Candado por terminal: dos tablets no pueden abrir la misma caja a la vez.
            Terminal::query()->lockForUpdate()->find($terminal->id);
            if ($terminal->openShift()) {
                throw new OrderException('Esta caja ya tiene un turno abierto.');
            }

            return Shift::create([
                'tenant_id' => $tenantId, 'terminal_id' => $terminal->id, 'opened_by_user_id' => $userId,
                'opened_at' => now(), 'opening_cash' => round($openingCash, 4), 'status' => 'open',
            ]);
        });
    }

    public function addMovement(Shift $shift, string $type, float $amount, string $reason, int $userId): CashMovement
    {
        if (!$shift->isOpen()) {
            throw new OrderException('El turno está cerrado.');
        }
        if (!in_array($type, ['in', 'out'], true) || $amount <= 0) {
            throw new OrderException('Indica un monto mayor a 0 y si es ingreso o egreso.');
        }
        if (trim($reason) === '') {
            throw new OrderException('Escribe el motivo del movimiento.');
        }

        return CashMovement::create([
            'tenant_id' => $shift->tenant_id, 'shift_id' => $shift->id, 'type' => $type,
            'amount' => round($amount, 4), 'reason' => mb_substr(trim($reason), 0, 160), 'user_id' => $userId,
        ]);
    }

    public function cashSales(Shift $shift): float
    {
        return (float) Payment::where('shift_id', $shift->id)->where('status', 'completed')
            ->whereIn('payment_method_id', function ($q) {
                $q->select('id')->from('aero_pos_payment_methods')->where('kind', 'cash');
            })->sum('amount');
    }

    public function expectedCash(Shift $shift): float
    {
        $in = (float) $shift->movements()->where('type', 'in')->sum('amount');
        $out = (float) $shift->movements()->where('type', 'out')->sum('amount');

        return round((float) $shift->opening_cash + $this->cashSales($shift) + $in - $out, 4);
    }

    public function close(Shift $shift, float $countedCash, int $userId, ?string $notes = null): Shift
    {
        if (!$shift->isOpen()) {
            throw new OrderException('El turno ya está cerrado.');
        }
        if ($countedCash < 0) {
            throw new OrderException('El efectivo contado no puede ser negativo.');
        }

        return Db::transaction(function () use ($shift, $countedCash, $userId, $notes) {
            $locked = Shift::query()->lockForUpdate()->findOrFail($shift->id);
            if (!$locked->isOpen()) {
                throw new OrderException('El turno ya está cerrado.');
            }

            $expected = $this->expectedCash($locked);
            $locked->fill([
                'expected_cash' => $expected, 'counted_cash' => round($countedCash, 4),
                'difference' => round($countedCash - $expected, 4), 'closed_by_user_id' => $userId,
                'closed_at' => now(), 'status' => 'closed', 'notes' => $notes,
            ])->save();

            // Reporte Z congelado: no cambia aunque luego se editen ventas.
            $locked->summary = ShiftReport::build($locked->fresh());
            $locked->save();

            return $locked->fresh();
        });
    }
}
