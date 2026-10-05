<?php namespace Aero\Workflows\Classes;

use Cache;
use Aero\Workflows\Jobs\RunWorkflowJob;
use Aero\Workflows\Models\Run;
use Aero\Workflows\Models\RunStep;
use Aero\Workflows\Models\Workflow;

/**
 * Motor: recorre el grafo desde el disparador. El tenant SIEMPRE es el del
 * workflow, nunca el que venga en la entrada. Límites duros: pasos, tiempo,
 * profundidad (workflow que dispara workflow) y ejecuciones por tenant/hora.
 */
class WorkflowRunner
{
    public const MAX_STEPS = 50;
    public const MAX_SECONDS = 60;
    public const MAX_DEPTH = 3;
    public const MAX_RUNS_PER_HOUR = 300;
    public const CREDIT_ACTION = 'workflows.run';

    protected static int $depth = 0;

    /**
     * Crea el run y lo ejecuta (sync) o lo encola. Devuelve null si el límite
     * por tenant/hora o la profundidad lo impiden.
     */
    public static function start(Workflow $workflow, array $payload, string $source, bool $sync = false): ?Run
    {
        if (static::$depth >= static::MAX_DEPTH || !static::withinHourlyLimit($workflow)) {
            \Log::warning('aero.workflows: ejecución descartada por límite', ['workflow_id' => $workflow->id]);

            return null;
        }

        $run = Run::create([
            'workflow_id'     => $workflow->id,
            'tenant_id'       => $workflow->tenant_id,
            'status'          => 'queued',
            'source'          => $source,
            'trigger_payload' => static::encode($payload),
        ]);

        if ($sync) {
            return static::execute($run);
        }

        RunWorkflowJob::dispatch($run->id);

        return $run;
    }

    public static function execute(Run $run, array $resume = []): Run
    {
        $workflow = $run->workflow;
        $graph = $workflow ? $workflow->jsonField('graph') : [];
        $nodes = [];

        foreach ((array) ($graph['nodes'] ?? []) as $node) {
            if (!empty($node['id'])) {
                $nodes[$node['id']] = $node;
            }
        }

        $edges = (array) ($graph['edges'] ?? []);

        $ctx = $run->decoded('context') ?: [
            'trigger' => $run->decoded('trigger_payload') ?: [],
            'vars'    => [],
            'nodes'   => [],
        ];

        $run->status = 'running';
        $run->started_at = $run->started_at ?: now();
        $run->save();

        static::$depth++;

        try {
            if (!$resume) {
                static::chargeCredits($run);
            }

            $queue = $resume ?: static::startNodes($nodes);

            if (!$queue) {
                throw new \RuntimeException('El workflow no tiene un nodo disparador.');
            }

            $deadline = microtime(true) + static::MAX_SECONDS;
            $steps = (int) $run->steps_count;
            $result = $run->decoded('result');

            while ($queue) {
                if ($steps >= static::MAX_STEPS) {
                    throw new \RuntimeException('Se superó el máximo de ' . static::MAX_STEPS . ' pasos.');
                }

                if (microtime(true) > $deadline) {
                    throw new \RuntimeException('Se superó el tiempo máximo de ejecución.');
                }

                $nodeId = array_shift($queue);
                $node = $nodes[$nodeId] ?? null;

                if (!$node) {
                    continue;
                }

                $steps++;
                $out = static::runNode($run, $node, $ctx);

                $ctx['nodes'][$nodeId] = $out['output'] ?? null;

                if (!empty($out['var'])) {
                    $ctx['vars'][$out['var']] = $out['output'];
                }

                if (array_key_exists('respond', $out)) {
                    $result = $out['respond'];
                }

                $next = static::nextNodes($edges, $nodeId, $out['handle'] ?? null);

                if (!empty($out['wait'])) {
                    $ctx['resume'] = array_merge($next, $queue);
                    $run->fill([
                        'status'      => 'waiting',
                        'context'     => static::encode($ctx),
                        'result'      => static::encode($result),
                        'steps_count' => $steps,
                    ])->save();

                    RunWorkflowJob::dispatch($run->id, true)->delay(now()->addSeconds($out['wait']));

                    return $run;
                }

                $queue = array_merge($queue, $next);
            }

            $run->fill([
                'status'      => 'ok',
                'context'     => static::encode($ctx),
                'result'      => static::encode($result ?? static::lastOutput($ctx)),
                'steps_count' => $steps,
                'finished_at' => now(),
            ])->save();
        }
        catch (\Throwable $e) {
            $run->fill([
                'status'      => 'error',
                'error'       => mb_substr($e->getMessage(), 0, 1000),
                'context'     => static::encode($ctx),
                'finished_at' => now(),
            ])->save();
        }
        finally {
            static::$depth--;
        }

        return $run;
    }

    /** Retoma un run en espera (nodo «Esperar»). */
    public static function resume(Run $run): Run
    {
        $ctx = $run->decoded('context') ?: [];
        $queue = (array) ($ctx['resume'] ?? []);

        unset($ctx['resume']);
        $run->context = static::encode($ctx);

        return static::execute($run, $queue ?: []);
    }

    protected static function runNode(Run $run, array $node, array $ctx): array
    {
        $type = (string) ($node['type'] ?? '');
        $definition = NodeRegistry::find($type);
        $started = microtime(true);

        $step = RunStep::create([
            'run_id'    => $run->id,
            'node_id'   => $node['id'],
            'node_type' => $type,
            'status'    => 'running',
        ]);

        try {
            if (!$definition || !is_callable($definition['handler'] ?? null)) {
                throw new \RuntimeException("Tipo de nodo desconocido: {$type}");
            }

            $data = TemplateResolver::resolve((array) ($node['data'] ?? []), $ctx);
            $out = (array) call_user_func($definition['handler'], $data, $ctx, $run->tenant_id, $run);

            $step->fill([
                'status'      => 'ok',
                'input'       => static::encode($data, 4000),
                'output'      => static::encode($out['output'] ?? null, 4000),
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            ])->save();

            return $out;
        }
        catch (\Throwable $e) {
            $step->fill([
                'status'      => 'error',
                'error'       => mb_substr($e->getMessage(), 0, 1000),
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            ])->save();

            throw $e;
        }
    }

    protected static function startNodes(array $nodes): array
    {
        foreach ($nodes as $id => $node) {
            if (str_starts_with((string) ($node['type'] ?? ''), 'trigger.')) {
                return [$id];
            }
        }

        return [];
    }

    /** Aristas que salen del nodo; con handle (condición) solo la rama elegida. */
    protected static function nextNodes(array $edges, string $nodeId, ?string $handle): array
    {
        $next = [];

        foreach ($edges as $edge) {
            if (($edge['source'] ?? null) !== $nodeId || empty($edge['target'])) {
                continue;
            }

            $edgeHandle = $edge['sourceHandle'] ?? null;

            if ($handle === null || $edgeHandle === null || $edgeHandle === '' || $edgeHandle === $handle) {
                $next[] = $edge['target'];
            }
        }

        return $next;
    }

    protected static function lastOutput(array $ctx): mixed
    {
        $nodes = $ctx['nodes'] ?? [];

        return $nodes ? end($nodes) : null;
    }

    /** Cobra una vez por ejecución (clave idempotente); sin Credits o sin acción, no hace nada. */
    protected static function chargeCredits(Run $run): void
    {
        if (!$run->tenant_id || !class_exists(\Aero\Credits\Classes\Credits::class)) {
            return;
        }

        try {
            \Aero\Credits\Classes\Credits::charge($run->tenant_id, static::CREDIT_ACTION, [
                'idempotency_key' => 'workflows.run.' . $run->id,
            ]);
        }
        catch (\Aero\Credits\Classes\Exceptions\InsufficientCreditsException $e) {
            throw new \RuntimeException('Créditos insuficientes para ejecutar el workflow.');
        }
        catch (\RuntimeException $e) {
            // La acción aún no existe en el catálogo: no bloquear la ejecución.
        }
    }

    protected static function withinHourlyLimit(Workflow $workflow): bool
    {
        $key = 'aero.workflows.runs.' . ($workflow->tenant_id ?: 0) . '.' . now()->format('YmdH');

        Cache::add($key, 0, 3700);

        return Cache::increment($key) <= static::MAX_RUNS_PER_HOUR;
    }

    protected static function encode(mixed $value, int $limit = 200000): ?string
    {
        if ($value === null) {
            return null;
        }

        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);

        return mb_strlen($json) > $limit ? mb_substr($json, 0, $limit) : $json;
    }
}
