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
 *             'handler'  => [Clase::class, 'metodo'], // (array $data, array $ctx, ?int $tenantId, Run $run): array
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

    public static function find(string $type): ?array
    {
        return static::all()[$type] ?? null;
    }

    public static function flush(): void
    {
        static::$nodes = null;
    }
}
