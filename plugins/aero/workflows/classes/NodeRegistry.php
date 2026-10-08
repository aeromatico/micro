<?php namespace Aero\Workflows\Classes;

use Event;

/**
 * Catálogo de nodos. Los básicos viven en BuiltinNodes; cualquier plugin suma
 * los suyos por evento:
 *
 *     Event::listen('aero.workflows.registerNodes', fn () => [
 *         'crm.create_contact' => [
 *             'label'    => 'CRM › Crear contacto',
 *             'category' => 'action',          // trigger | logic | action
 *             'handler'  => [Clase::class, 'metodo'], // (array $data, array $ctx, ?int $tenantId, Run $run, array $node): array
 *         ],
 *     ]);
 *
 * El handler devuelve ['output' => mixed, 'handle' => ?string, 'wait' => ?int,
 * 'respond' => mixed]. El tenant_id lo pone el runner (el del workflow).
 */
class NodeRegistry
{
    protected static ?array $nodes = null;

    public static function all(): array
    {
        if (static::$nodes !== null) {
            return static::$nodes;
        }

        $nodes = BuiltinNodes::definitions();

        foreach ((array) Event::fire('aero.workflows.registerNodes') as $result) {
            if (is_array($result)) {
                $nodes = array_merge($nodes, $result);
            }
        }

        return static::$nodes = $nodes;
    }

    /**
     * Nodos que este tenant puede usar. Un nodo puede declarar
     * `'available' => callable(?int $tenantId): bool` (p. ej. solo para el master);
     * sin esa clave está disponible para todos. El editor y el constructor con IA
     * ofrecen solo estos; el handler del nodo vuelve a comprobarlo al ejecutar.
     */
    public static function availableFor(?int $tenantId): array
    {
        return array_filter(static::all(), function ($node) use ($tenantId) {
            $gate = $node['available'] ?? null;

            return !is_callable($gate) || (bool) call_user_func($gate, $tenantId);
        });
    }

    public static function find(string $type): ?array
    {
        return static::all()[$type] ?? null;
    }

    public static function flush(): void
    {
        static::$nodes = null;
    }
}
