<?php namespace Aero\Workflows\Classes;

/**
 * Genera el catálogo de nodos para el skill del agente de workflows a partir
 * de NodeRegistry, así el skill nunca se queda atrás cuando un plugin agrega
 * nodos. Lo corre `php artisan workflows:skill-catalog`.
 */
class SkillCatalog
{
    protected const CATEGORY_ORDER = ['trigger' => 1, 'logic' => 2, 'action' => 3];

    public static function render(): string
    {
        $nodes = NodeRegistry::all();

        uksort($nodes, function ($a, $b) use ($nodes) {
            $ca = static::CATEGORY_ORDER[$nodes[$a]['category'] ?? ''] ?? 9;
            $cb = static::CATEGORY_ORDER[$nodes[$b]['category'] ?? ''] ?? 9;

            return [$ca, $a] <=> [$cb, $b];
        });

        $out = [
            '# Catálogo de nodos de Aero.Workflows',
            '',
            '> Generado automáticamente desde `NodeRegistry` por `php artisan workflows:skill-catalog`. No editar a mano.',
            '',
            'Cada nodo tiene `id`, `type`, `position` y `data`. `data` guarda los campos de la tabla. Las conexiones (`edges`) pueden llevar `sourceHandle` para elegir la salida.',
            '',
            '⚠ = nodo con efectos (cobra, envía o llama URLs). En un **borrador automático** no se permite; lo aprueba una persona.',
            '',
        ];

        foreach ($nodes as $type => $def) {
            $flag = in_array($type, GraphValidator::SIDE_EFFECT_TYPES, true) ? ' ⚠' : '';
            $out[] = "## `{$type}`{$flag}";
            $out[] = '';
            $out[] = "- **Nombre:** " . ($def['label'] ?? $type);
            $out[] = '- **Categoría:** ' . ($def['category'] ?? '—');

            if (!empty($def['handles'])) {
                $handles = array_map(fn ($h) => "`{$h['id']}` ({$h['label']})", $def['handles']);
                $out[] = '- **Salidas (`sourceHandle`):** ' . implode(', ', $handles);
            }
            else {
                $out[] = '- **Salidas:** una sola; la conexión no lleva `sourceHandle`.';
            }

            $fields = $def['fields'] ?? [];

            if ($fields) {
                $out[] = '';
                $out[] = '| Campo (`data.key`) | Tipo | Opciones | Ayuda |';
                $out[] = '|---|---|---|---|';

                foreach ($fields as $f) {
                    $options = isset($f['options']) ? implode(', ', array_map(fn ($o) => "`{$o['value']}`", $f['options'])) : '';
                    $hint = str_replace('|', '\\|', (string) ($f['hint'] ?? ''));
                    $out[] = "| `{$f['key']}` ({$f['label']}) | " . ($f['type'] ?? 'text') . " | {$options} | {$hint} |";
                }
            }

            $out[] = '';
        }

        return implode("\n", $out);
    }
}
