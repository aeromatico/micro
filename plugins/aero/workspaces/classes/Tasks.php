<?php namespace Aero\Workspaces\Classes;

use Aero\Workspaces\Models\Settings;
use Aero\Workspaces\Models\Staff;
use Aero\Workspaces\Models\Task;

/**
 * Encargos de la Oficina. El orquestador recibe el encargo, lo reparte entre
 * todo el equipo del tenant (él + los contratados) y cada uno «trabaja» un rato.
 *
 * IMPORTANTE: la ejecución es una SIMULACIÓN. No existe todavía un motor que
 * haga el trabajo de cada agente; el encargo se registra, se cobra (si el cobro
 * está encendido) y su estado avanza por el reloj. No entrega resultados.
 */
class Tasks
{
    public const MAX_BRIEF = 2000;
    public const MEET_SECONDS = 6;
    public const WRAP_SECONDS = 4;

    protected const VERBS = [
        'video'          => ['Montando el video', 'Video listo'],
        'imagen'         => ['Diseñando las imágenes', 'Imágenes listas'],
        'contenido'      => ['Escribiendo el contenido', 'Contenido listo'],
        'info'           => ['Investigando el tema', 'Investigación lista'],
        'ppt'            => ['Maquetando la presentación', 'Presentación lista'],
        'automatizacion' => ['Diseñando el flujo', 'Flujo listo'],
        'ecommerce'      => ['Preparando la tienda', 'Tienda lista'],
        'web'            => ['Desarrollando el sitio', 'Sitio listo'],
        'integraciones' => ['Conectando los sistemas', 'Integración lista'],
    ];

    /** 1 + largo/400: los encargos largos cuestan más (igual que el prototipo). */
    public static function factor(string $brief): float
    {
        return 1 + mb_strlen($brief) / 400;
    }

    /**
     * Plan de un encargo: un paso por miembro del equipo.
     *
     * @return array{steps: array, estimated_points: int, total_seconds: int, orchestrator: ?Staff}
     * @throws \DomainException
     */
    public static function plan(int $tenantId, string $brief): array
    {
        $brief = trim($brief);

        if ($brief === '') {
            throw new \DomainException('Cuéntale al orquestador qué quieres lograr.');
        }

        if (mb_strlen($brief) > static::MAX_BRIEF) {
            throw new \DomainException('El encargo es demasiado largo (máximo ' . static::MAX_BRIEF . ' caracteres).');
        }

        $orchestrator = Staff::orchestrator();

        if (!$orchestrator) {
            throw new \DomainException('Todavía no hay un orquestador configurado.');
        }

        $factor = static::factor($brief);
        $steps = [];
        $longest = 0;

        foreach (Workspace::team($tenantId) as $member) {
            $isLead = $member['orchestrator'];
            [$label, $done] = $isLead ? ['Coordinando al equipo', 'Entrega lista'] : (static::VERBS[$member['category']] ?? ['Trabajando en el encargo', 'Entrega lista']);
            $dur = $isLead ? 15 : 8 + (crc32($member['slug']) % 8);
            $longest = max($longest, $dur);

            $steps[] = [
                'staff_id' => $member['id'], 'slug' => $member['slug'], 'name' => $member['name'], 'role' => $member['role'],
                'avatar' => $member['avatar'], 'lead' => $isLead, 'label' => $label, 'done' => $done, 'seconds' => $dur,
                'points' => (int) round($member['task_fee'] * $factor),
            ];
        }

        return [
            'steps'            => $steps,
            'estimated_points' => array_sum(array_column($steps, 'points')),
            'total_seconds'    => static::MEET_SECONDS + $longest + static::WRAP_SECONDS,
            'orchestrator'     => $orchestrator,
        ];
    }

    /** Qué costaría y quién haría qué, sin crear nada. */
    public static function preview(int $tenantId, string $brief): array
    {
        $plan = static::plan($tenantId, $brief);

        return [
            'estimated_points' => $plan['estimated_points'],
            'will_charge'      => Settings::chargeEnabled() ? $plan['estimated_points'] : 0,
            'points'           => Workspace::points($tenantId),
            'team'             => array_map(fn ($s) => ['name' => $s['name'], 'role' => $s['role'], 'does' => $s['label']], $plan['steps']),
            'simulated'        => true,
        ];
    }

    /**
     * Crea el encargo (y lo cobra si corresponde). Un solo encargo en curso por tenant.
     *
     * @throws \DomainException
     */
    public static function submit(int $tenantId, string $brief, ?int $userId = null, string $source = 'office'): Task
    {
        Task::settleDue($tenantId);

        if (Task::forTenant($tenantId)->where('status', 'running')->exists()) {
            throw new \DomainException('Tu equipo ya está trabajando en un encargo. Espera a que termine.');
        }

        $plan = static::plan($tenantId, $brief);
        $total = $plan['estimated_points'];

        try {
            return \DB::transaction(function () use ($tenantId, $brief, $userId, $source, $plan, $total) {
                $task = Task::create([
                    'tenant_id' => $tenantId, 'user_id' => $userId, 'orchestrator_id' => $plan['orchestrator']->id,
                    'brief' => trim($brief), 'status' => 'running', 'estimated_points' => $total, 'charged_points' => 0,
                    'plan' => ['steps' => $plan['steps'], 'meet_seconds' => static::MEET_SECONDS, 'total_seconds' => $plan['total_seconds']],
                    'source' => $source, 'started_at' => now(), 'finished_at' => now()->addSeconds($plan['total_seconds']),
                ]);

                if ($total > 0 && Settings::chargeEnabled() && class_exists(\Aero\Credits\Classes\Credits::class)) {
                    $type = \Aero\Credits\Models\CreditType::findByCode(Settings::creditTypeCode());

                    if (!$type) {
                        throw new \DomainException('El tipo de crédito de Workspaces no está configurado.');
                    }

                    $tx = \Aero\Credits\Classes\Credits::chargeRaw($tenantId, $type, $total, 'workspaces.task', [
                        'source_plugin'   => 'Aero.Workspaces',
                        'reason'          => 'Encargo #' . $task->id,
                        'idempotency_key' => 'workspaces.task.' . $task->id,
                    ]);

                    $task->update(['charged_points' => $total, 'credit_transaction_id' => $tx->id]);
                }

                return $task;
            });
        }
        catch (\Aero\Credits\Classes\Exceptions\InsufficientCreditsException $e) {
            throw new \DomainException("No tienes puntos suficientes para este encargo ({$total} pts).");
        }
    }

    public static function recent(int $tenantId, int $limit = 10): array
    {
        Task::settleDue($tenantId);

        return Task::forTenant($tenantId)->orderByDesc('id')->limit(max(1, min(50, $limit)))->get()
            ->map(fn (Task $t) => static::payload($t))->all();
    }

    public static function find(int $tenantId, int $id): ?array
    {
        Task::settleDue($tenantId);
        $task = Task::forTenant($tenantId)->find($id);

        return $task ? static::payload($task) : null;
    }

    public static function payload(Task $task): array
    {
        $plan = (array) $task->plan;
        $elapsed = $task->started_at ? max(0, now()->diffInSeconds($task->started_at, true)) : 0;

        return [
            'id'               => (int) $task->id,
            'brief'            => $task->brief,
            'status'           => $task->status,
            'simulated'        => true,
            'estimated_points' => (int) $task->estimated_points,
            'charged_points'   => (int) $task->charged_points,
            'steps'            => $plan['steps'] ?? [],
            'meet_seconds'     => (int) ($plan['meet_seconds'] ?? static::MEET_SECONDS),
            'total_seconds'    => (int) ($plan['total_seconds'] ?? 0),
            'elapsed_seconds'  => $task->status === 'running' ? (int) min($elapsed, (int) ($plan['total_seconds'] ?? 0)) : (int) ($plan['total_seconds'] ?? 0),
            'started_at'       => optional($task->started_at)->toIso8601String(),
            'finished_at'      => optional($task->finished_at)->toIso8601String(),
            'source'           => $task->source,
        ];
    }
}
