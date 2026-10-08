<?php namespace Aero\Workspaces\Classes;

use Aero\Workspaces\Models\Hire;
use Aero\Workspaces\Models\Message;
use Aero\Workspaces\Models\Settings;
use Aero\Workspaces\Models\Staff;
use Aero\Workspaces\Models\Task;

/**
 * Todo el cobro de Workspaces en un solo lugar. Los puntos son créditos de
 * Aero.Credits (tipo configurable). Con el cobro apagado nada de esto cobra.
 *
 * Tarifas por agente (tabla de tarifas por tarea):
 *  - `encargo`   puntos por encargo a la Oficina (se multiplica por el largo del brief).
 *  - `chat_turn` puntos por cada mensaje que el agente responde de verdad.
 * Más la tarifa de contratación (`hire_fee`).
 *
 * Eventos: `aero.workspaces.charged` y `aero.workspaces.refunded`
 *   args: [tenantId, concepto (hire|task|turn), puntos, staffId|null, referencia]
 */
class Billing
{
    public const TASK = 'encargo';
    public const TURN = 'chat_turn';

    /** Catálogo cerrado de tipos de tarea que el sistema sabe cobrar. */
    public const TYPES = [
        self::TASK => 'Encargo a la Oficina',
        self::TURN => 'Mensaje de chat (turno real)',
    ];

    /** ¿Se cobra de verdad? Cobro encendido y Aero.Credits presente. */
    public static function active(): bool
    {
        return Settings::chargeEnabled() && class_exists(\Aero\Credits\Classes\Credits::class);
    }

    /** Libro de puntos (solo pruebas: se reemplaza por uno falso). */
    public static ?object $ledger = null;

    public static function ledger(): object
    {
        return static::$ledger ??= new CreditsLedger();
    }

    /**
     * Cobra puntos y devuelve el id del movimiento.
     *
     * @throws \Aero\Credits\Classes\Exceptions\InsufficientCreditsException
     * @throws \DomainException
     */
    public static function charge(int $tenantId, int $amount, string $actionCode, string $reason, string $idempotencyKey, array $extra = []): int
    {
        return static::ledger()->charge($tenantId, $amount, $actionCode, ['reason' => $reason, 'idempotency_key' => $idempotencyKey] + $extra);
    }

    /** Tarifa en puntos de un agente para un tipo de tarea (0 = gratis). */
    public static function rate(Staff $staff, string $type): int
    {
        return (int) round((float) ($staff->taskRateRows->firstWhere('task_type', $type)->fee ?? 0));
    }

    /** Puntos que costaría el próximo mensaje a este agente (0 con el cobro apagado). */
    public static function turnCost(Staff $staff): int
    {
        return static::active() ? static::rate($staff, static::TURN) : 0;
    }

    /**
     * Antes de dejar que un agente trabaje: ¿alcanza el saldo para su mensaje?
     *
     * @throws \DomainException
     */
    public static function assertCanAffordTurn(int $tenantId, Staff $staff): void
    {
        $cost = static::turnCost($staff);

        if ($cost <= 0) {
            return;
        }

        $balance = static::ledger()->balance($tenantId);

        if ($balance < $cost) {
            throw new \DomainException("No tienes puntos suficientes para hablar con {$staff->name} ({$cost} pts por mensaje, tienes {$balance}).");
        }
    }

    /**
     * Cobra un mensaje ya respondido. Nunca lanza ni quita la respuesta: si el
     * saldo cambió en el camino, se registra y la respuesta se entrega igual.
     */
    public static function chargeTurn(Message $reply, Staff $staff): void
    {
        try {
            $cost = static::turnCost($staff);

            if ($cost <= 0 || $reply->charged_points > 0) {
                return;
            }

            $tenantId = (int) $reply->tenant_id;
            $txId = static::charge($tenantId, $cost, 'workspaces.turn', "Mensaje con {$staff->name}", "workspaces.turn.{$reply->id}", ['staff_id' => $staff->id, 'message_id' => $reply->id]);

            $reply->update(['charged_points' => $cost, 'credit_transaction_id' => $txId]);
            \Event::fire('aero.workspaces.charged', [$tenantId, 'turn', $cost, (int) $staff->id, (int) $reply->id]);
        }
        catch (\Throwable $e) {
            \Log::warning('aero.workspaces: no se pudo cobrar el mensaje', ['message_id' => $reply->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Cancela un encargo en curso y devuelve los puntos cobrados (la ejecución
     * es simulada: no se entregó nada).
     *
     * @throws \DomainException
     */
    public static function cancelTask(int $tenantId, int $taskId): Task
    {
        Task::settleDue($tenantId);
        $task = Task::forTenant($tenantId)->find($taskId);

        if (!$task) {
            throw new \DomainException('No existe ese encargo.');
        }

        if ($task->status !== 'running') {
            throw new \DomainException('Ese encargo ya terminó o fue cancelado.');
        }

        \DB::transaction(function () use ($task, $tenantId) {
            $refunded = 0;

            if ($task->credit_transaction_id && $task->charged_points > 0 && static::active()) {
                static::ledger()->refund((int) $task->credit_transaction_id, "Encargo #{$task->id} cancelado");
                $refunded = (int) $task->charged_points;
            }

            $task->update(['status' => 'cancelled', 'finished_at' => now(), 'refunded_at' => $refunded > 0 ? now() : null]);

            if ($refunded > 0) {
                \Event::fire('aero.workspaces.refunded', [$tenantId, 'task', $refunded, null, (int) $task->id]);
            }
        });

        return $task->fresh();
    }

    /** Resumen de cobros para el superadmin: por concepto, por agente y por tenant, en los últimos N días. */
    public static function report(int $days = 30): array
    {
        $since = now()->subDays(max(1, $days));

        $hires = Hire::where('hired_at', '>=', $since)->where('fee_charged', '>', 0);
        $turns = Message::where('created_at', '>=', $since)->where('charged_points', '>', 0);
        $tasks = Task::where('created_at', '>=', $since)->where('charged_points', '>', 0);

        $byStaff = [];

        foreach ((clone $hires)->selectRaw('staff_id, sum(fee_charged) p, count(*) n')->groupBy('staff_id')->get() as $r) {
            $byStaff[$r->staff_id]['hire'] = (int) $r->p;
            $byStaff[$r->staff_id]['hires'] = (int) $r->n;
        }

        foreach ((clone $turns)->selectRaw('staff_id, sum(charged_points) p, count(*) n')->groupBy('staff_id')->get() as $r) {
            $byStaff[$r->staff_id]['turns'] = (int) $r->p;
            $byStaff[$r->staff_id]['messages'] = (int) $r->n;
        }

        $names = Staff::whereIn('id', array_keys($byStaff) ?: [0])->pluck('name', 'id');
        $agents = [];

        foreach ($byStaff as $id => $v) {
            $v += ['hire' => 0, 'hires' => 0, 'turns' => 0, 'messages' => 0];
            $agents[] = ['name' => $names[$id] ?? "#{$id}", 'total' => $v['hire'] + $v['turns']] + $v;
        }

        usort($agents, fn ($a, $b) => $b['total'] <=> $a['total']);

        $tenants = [];

        foreach ([['hire', (clone $hires), 'fee_charged'], ['turn', (clone $turns), 'charged_points'], ['task', (clone $tasks), 'charged_points']] as [$kind, $q, $col]) {
            foreach ($q->selectRaw("tenant_id, sum({$col}) p")->groupBy('tenant_id')->pluck('p', 'tenant_id') as $tid => $p) {
                $tenants[$tid][$kind] = (int) $p;
            }
        }

        $tenantRows = [];

        foreach ($tenants as $tid => $v) {
            $v += ['hire' => 0, 'turn' => 0, 'task' => 0];
            $tenantRows[] = ['tenant_id' => (int) $tid, 'total' => array_sum($v)] + $v;
        }

        usort($tenantRows, fn ($a, $b) => $b['total'] <=> $a['total']);

        $refunded = (int) Task::where('refunded_at', '>=', $since)->sum('charged_points');
        $totals = [
            'hire' => (int) (clone $hires)->sum('fee_charged'),
            'turn' => (int) (clone $turns)->sum('charged_points'),
            'task' => (int) (clone $tasks)->sum('charged_points'),
        ];

        return [
            'days'      => $days,
            'charging'  => Settings::chargeEnabled(),
            'totals'    => $totals + ['refunded' => $refunded, 'net' => array_sum($totals) - $refunded],
            'agents'    => $agents,
            'tenants'   => $tenantRows,
        ];
    }
}
