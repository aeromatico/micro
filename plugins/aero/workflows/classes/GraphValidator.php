<?php namespace Aero\Workflows\Classes;

/**
 * Valida el grafo de un workflow antes de guardarlo o publicarlo. Lo usa el
 * editor, el agente que genera borradores y la publicación.
 *
 * Devuelve la lista de errores (vacía = válido). Cada error es un mensaje en
 * español que se puede mostrar tal cual al revisor.
 *
 * Con `$draft = true` (flujos generados por IA) además rechaza los nodos con
 * efectos: cobran, envían mensajes o llaman URLs. Esos los agrega y aprueba
 * una persona.
 */
class GraphValidator
{
    /** Nodos que cobran, escriben o salen del sistema. Prohibidos en borradores automáticos. */
    public const SIDE_EFFECT_TYPES = [
        'shop.checkout',
        'shop.cart_add',
        'action.message',
        'action.reply',
        'action.notify',
        'action.http',
    ];

    public const MAX_NODES = 60;

    public static function validate(array $graph, bool $draft = false): array
    {
        $errors = [];

        $nodes = $graph['nodes'] ?? null;
        $edges = $graph['edges'] ?? [];

        if (!is_array($nodes) || $nodes === []) {
            return ['El flujo no tiene nodos.'];
        }

        if (count($nodes) > static::MAX_NODES) {
            return ["El flujo tiene más de " . static::MAX_NODES . " nodos."];
        }

        if (!is_array($edges)) {
            return ['Las conexiones (edges) deben ser una lista.'];
        }

        $registry = NodeRegistry::all();
        $byId = [];
        $triggers = [];

        foreach ($nodes as $i => $node) {
            $id = $node['id'] ?? null;
            $type = $node['type'] ?? null;

            if (!is_string($id) || $id === '') {
                $errors[] = "El nodo #{$i} no tiene id.";
                continue;
            }

            if (isset($byId[$id])) {
                $errors[] = "El id de nodo «{$id}» está repetido.";
                continue;
            }

            if (!is_string($type) || !isset($registry[$type])) {
                $errors[] = "El nodo «{$id}» tiene un tipo desconocido: " . (is_string($type) ? $type : 'vacío') . '.';
                $byId[$id] = null;
                continue;
            }

            if (!array_key_exists('data', $node) || !is_array($node['data'])) {
                $errors[] = "El nodo «{$id}» debe tener «data» como objeto.";
            }

            if ($draft && in_array($type, static::SIDE_EFFECT_TYPES, true)) {
                $errors[] = "El nodo «{$id}» ({$type}) tiene efectos y no se puede usar en un borrador automático; lo debe aprobar una persona.";
            }

            foreach ((array) ($node['data'] ?? []) as $key => $value) {
                if (is_string($value) && substr_count($value, '{{') !== substr_count($value, '}}')) {
                    $errors[] = "En el nodo «{$id}», el campo «{$key}» tiene una plantilla {{ }} sin cerrar.";
                }
            }

            if (($registry[$type]['category'] ?? null) === 'trigger') {
                $triggers[] = $id;
            }

            $byId[$id] = $registry[$type];
        }

        if (count($triggers) !== 1) {
            $errors[] = 'El flujo debe tener exactamente un disparador (tiene ' . count($triggers) . ').';
        }

        $outgoing = [];
        $usedHandles = [];

        foreach ($edges as $i => $edge) {
            $source = $edge['source'] ?? null;
            $target = $edge['target'] ?? null;

            if (!array_key_exists($source ?? '', $byId) || !array_key_exists($target ?? '', $byId)) {
                $errors[] = "La conexión #{$i} apunta a un nodo que no existe.";
                continue;
            }

            if ($source === $target) {
                $errors[] = "El nodo «{$source}» se conecta consigo mismo.";
                continue;
            }

            $handle = $edge['sourceHandle'] ?? null;
            $handles = ($byId[$source] ?? [])['handles'] ?? null;

            if ($handles) {
                $valid = array_column($handles, 'id');

                if ($handle === null || !in_array($handle, $valid, true)) {
                    $errors[] = "La salida de «{$source}» debe usar una de estas salidas: " . implode(', ', $valid) . '.';
                }
            }
            elseif ($handle !== null && $handle !== 'default') {
                $errors[] = "El nodo «{$source}» no tiene la salida «{$handle}».";
            }

            $outgoing[$source][] = $target;
            $usedHandles[$source][] = $handle ?? 'default';
        }

        // Cada salida de un nodo debe estar conectada: si no, el flujo termina en silencio por ese camino.
        foreach ($byId as $id => $def) {
            foreach ((array) ($def['handles'] ?? []) as $h) {
                if (!in_array($h['id'], $usedHandles[$id] ?? [], true)) {
                    $errors[] = "La salida «{$h['id']}» de «{$id}» no está conectada: el flujo terminaría sin respuesta por ese camino.";
                }
            }
        }

        if ($errors) {
            return $errors;
        }

        // Ciclos: un flujo debe terminar.
        if (static::hasCycle($outgoing, array_keys($byId))) {
            $errors[] = 'El flujo tiene un ciclo: no puede volver a un nodo anterior.';
        }

        // Todo nodo debe ser alcanzable desde el disparador.
        if (count($triggers) === 1) {
            $reached = static::reachable($outgoing, $triggers[0]);

            foreach (array_keys($byId) as $id) {
                if (!isset($reached[$id])) {
                    $errors[] = "El nodo «{$id}» no está conectado al disparador.";
                }
            }
        }

        return $errors;
    }

    protected static function hasCycle(array $outgoing, array $ids): bool
    {
        $state = [];

        $visit = function (string $node) use (&$visit, &$state, $outgoing): bool {
            if (($state[$node] ?? 0) === 1) {
                return true;
            }

            if (($state[$node] ?? 0) === 2) {
                return false;
            }

            $state[$node] = 1;

            foreach ($outgoing[$node] ?? [] as $next) {
                if ($visit($next)) {
                    return true;
                }
            }

            $state[$node] = 2;

            return false;
        };

        foreach ($ids as $id) {
            if ($visit((string) $id)) {
                return true;
            }
        }

        return false;
    }

    protected static function reachable(array $outgoing, string $start): array
    {
        $seen = [$start => true];
        $stack = [$start];

        while ($stack) {
            $node = array_pop($stack);

            foreach ($outgoing[$node] ?? [] as $next) {
                if (!isset($seen[$next])) {
                    $seen[$next] = true;
                    $stack[] = $next;
                }
            }
        }

        return $seen;
    }
}
