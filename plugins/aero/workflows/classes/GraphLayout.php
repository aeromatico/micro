<?php namespace Aero\Workflows\Classes;

/**
 * Acomoda los nodos de un grafo por capas: cada nodo queda debajo de todo lo
 * que lo alimenta (camino más largo desde el disparador) y dentro de cada capa
 * se ordena por el promedio de posición de sus vecinos, para cruzar menos
 * líneas. Es el mismo algoritmo del botón «Ordenar» del editor. Solo cambia
 * `position`; nodos y conexiones no se tocan.
 */
class GraphLayout
{
    public static function apply(array $graph, int $gapX = 300, int $gapY = 150, int $centerX = 700): array
    {
        $nodes = [];

        foreach ((array) ($graph['nodes'] ?? []) as $node) {
            if (!empty($node['id'])) {
                $nodes[$node['id']] = $node;
            }
        }

        $edges = array_values(array_filter((array) ($graph['edges'] ?? []), fn ($e) => isset($nodes[$e['source'] ?? null], $nodes[$e['target'] ?? null])));
        $parents = array_fill_keys(array_keys($nodes), []);
        $children = array_fill_keys(array_keys($nodes), []);

        foreach ($edges as $edge) {
            $parents[$edge['target']][] = $edge['source'];
            $children[$edge['source']][] = $edge['target'];
        }

        // Las conexiones que cierran un ciclo no cuentan para las capas (si no, empujarían hacia abajo a todo el ciclo).
        $forward = static::withoutBackEdges($nodes, $edges, $children);

        // Capa = camino más largo desde una raíz.
        $level = array_fill_keys(array_keys($nodes), 0);

        for ($pass = 0, $max = count($nodes); $pass < $max; $pass++) {
            $changed = false;

            foreach ($forward as $edge) {
                if ($level[$edge['target']] < $level[$edge['source']] + 1) {
                    $level[$edge['target']] = $level[$edge['source']] + 1;
                    $changed = true;
                }
            }

            if (!$changed) {
                break;
            }
        }

        $layers = [];

        foreach (array_keys($nodes) as $id) {
            $layers[$level[$id]][] = $id;
        }

        ksort($layers);
        $index = [];

        foreach ($layers as $depth => $ids) {
            foreach ($ids as $i => $id) {
                $index[$id] = $i;
            }
        }

        // Barridos hacia abajo y hacia arriba: ordenar cada capa por el promedio de sus vecinos.
        for ($sweep = 0; $sweep < 4; $sweep++) {
            $down = $sweep % 2 === 0;
            $order = $down ? array_keys($layers) : array_reverse(array_keys($layers));

            foreach ($order as $depth) {
                $neighbours = $down ? $parents : $children;
                $bary = [];

                foreach ($layers[$depth] as $position => $id) {
                    $known = array_filter($neighbours[$id], fn ($n) => ($level[$n] ?? -1) === ($down ? $depth - 1 : $depth + 1));
                    $bary[$id] = $known ? array_sum(array_map(fn ($n) => $index[$n], $known)) / count($known) : $position;
                }

                $ids = $layers[$depth];
                usort($ids, fn ($a, $b) => [$bary[$a], $index[$a]] <=> [$bary[$b], $index[$b]]);
                $layers[$depth] = $ids;

                foreach ($ids as $i => $id) {
                    $index[$id] = $i;
                }
            }
        }

        foreach ($layers as $depth => $ids) {
            foreach ($ids as $i => $id) {
                $nodes[$id]['position'] = ['x' => (int) round(($i - (count($ids) - 1) / 2) * $gapX) + $centerX, 'y' => $depth * $gapY];
            }
        }

        return ['nodes' => array_values($nodes), 'edges' => (array) ($graph['edges'] ?? [])];
    }

    /** @return array<int, array> las conexiones sin las que vuelven a un nodo que sigue «abierto» en el recorrido. */
    protected static function withoutBackEdges(array $nodes, array $edges, array $children): array
    {
        $state = [];
        $back = [];

        $visit = function (string $id) use (&$visit, &$state, &$back, $children): void {
            $state[$id] = 1;

            foreach ($children[$id] as $child) {
                if (($state[$child] ?? 0) === 1) {
                    $back[$id . '>' . $child] = true;
                }
                elseif (!isset($state[$child])) {
                    $visit($child);
                }
            }

            $state[$id] = 2;
        };

        // Primero desde el disparador; luego desde lo que quede sin visitar.
        $roots = array_keys(array_filter($nodes, fn ($n) => str_starts_with((string) ($n['type'] ?? ''), 'trigger.')));

        foreach (array_merge($roots, array_keys($nodes)) as $id) {
            if (!isset($state[$id])) {
                $visit($id);
            }
        }

        return array_values(array_filter($edges, fn ($e) => !isset($back[$e['source'] . '>' . $e['target']])));
    }
}
