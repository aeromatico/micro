<?php namespace Aero\Mcp\Console;

use Aero\Mcp\Classes\McpResourceTools;
use Aero\Mcp\Classes\McpToolRegistry;
use Illuminate\Console\Command;
use Schema;

/**
 * Auditoría permanente de la cobertura MCP:
 *
 *   php artisan mcp:audit [--plugin=crm]
 *
 *  - toda tabla de tenant (columna tenant_id) debe estar declarada como recurso
 *    o excluida con motivo en resources/_excluded.php;
 *  - toda columna declarada (fields/search/filters/writable) debe existir;
 *  - nada declarado puede coincidir con la lista negra de nombres sensibles.
 *
 * Sale con 1 si hay errores, para usarlo en CI / antes de dar un plugin por
 * terminado. Correr como `www`, no como root.
 */
class Audit extends Command
{
    protected $name = 'mcp:audit';

    protected $description = 'Audita la cobertura y seguridad de los recursos expuestos por el MCP.';

    protected function getOptions()
    {
        return [['plugin', null, \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL, 'Limitar a un plugin (carpeta, p. ej. crm).']];
    }

    public function handle(): int
    {
        $only = $this->option('plugin') ? strtolower((string) $this->option('plugin')) : null;
        $errors = $warnings = 0;
        $declaredModels = [];

        foreach (McpToolRegistry::declared() as $key => $def) {
            $model = $def['model'] ?? null;

            if (!is_string($model) || !class_exists($model)) {
                $this->line("  · {$key}: modelo no disponible (plugin ausente), se omite.");
                continue;
            }

            $declaredModels[ltrim($model, '\\')] = true;

            if ($only && $this->pluginOf($model) !== $only) {
                continue;
            }

            $instance = new $model;
            $table = $instance->getTable();

            if (!Schema::hasTable($table)) {
                $this->error("  ✗ {$key}: no existe la tabla {$table}.");
                $errors++;
                continue;
            }

            $columns = Schema::getColumnListing($table);

            if (empty($def['tenant_via']) && !in_array($def['tenant_column'] ?? 'tenant_id', $columns, true)) {
                $this->error("  ✗ {$key}: {$table} no tiene columna de tenant.");
                $errors++;
            }

            foreach (['fields', 'search', 'filters', 'writable'] as $kind) {
                $list = $def[$kind] ?? [];
                $list = is_array($list) ? $list : array_filter(array_map('trim', explode(',', (string) $list)));

                foreach ($list as $column) {
                    if (!in_array($column, $columns, true)) {
                        $this->error("  ✗ {$key}: {$kind} → columna inexistente '{$column}' en {$table}.");
                        $errors++;
                    } elseif (McpResourceTools::isDenied($column)) {
                        $this->warn("  ! {$key}: {$kind} → '{$column}' es sensible y se descarta.");
                        $warnings++;
                    }
                }
            }

            if (!empty($def['tenant_via']) && !empty($def['writable'])) {
                $this->warn("  ! {$key}: un recurso hijo (tenant_via) nunca es escribible; se ignora 'writable'.");
                $warnings++;
            }
        }

        $excluded = array_map(fn ($c) => ltrim($c, '\\'), array_keys(McpToolRegistry::excluded()));

        foreach ($excluded as $class) {
            if (!class_exists($class)) {
                $this->warn("  ! _excluded.php: {$class} ya no existe.");
                $warnings++;
            }
        }

        $uncovered = [];

        foreach ($this->tenantModels() as $class => $table) {
            if ($only && $this->pluginOf($class) !== $only) {
                continue;
            }
            if (!isset($declaredModels[$class]) && !in_array($class, $excluded, true)) {
                $uncovered[] = "{$class} ({$table})";
            }
        }

        foreach ($uncovered as $line) {
            $this->error("  ✗ sin cobertura MCP: {$line}");
            $errors++;
        }

        $this->info(sprintf(
            'mcp:audit — %d recursos, %d modelos de tenant excluidos, %d errores, %d avisos.',
            count(McpToolRegistry::resources()),
            count($excluded),
            $errors,
            $warnings
        ));

        if ($uncovered) {
            $this->line('Declara el modelo en plugins/aero/mcp/resources/<plugin>.php o agrégalo a _excluded.php con el motivo.');
        }

        return $errors ? 1 : 0;
    }

    /** class => table para cada modelo de plugins/aero/*\/models con columna tenant_id. */
    protected function tenantModels(): array
    {
        $found = [];

        foreach (glob(plugins_path('aero/*/models/*.php')) ?: [] as $file) {
            $src = (string) file_get_contents($file);

            if (!preg_match('/^namespace\s+([^;]+);/m', $src, $ns) || !preg_match('/^(?:abstract\s+)?class\s+(\w+)/m', $src, $cls)) {
                continue;
            }

            $class = $ns[1] . '\\' . $cls[1];

            if (!class_exists($class) || !is_subclass_of($class, \Illuminate\Database\Eloquent\Model::class)) {
                continue;
            }

            try {
                $table = (new $class)->getTable();
                if (Schema::hasTable($table) && Schema::hasColumn($table, 'tenant_id')) {
                    $found[$class] = $table;
                }
            } catch (\Throwable $e) {
                // modelo sin tabla propia (abstracto, vista…): no es de tenant
            }
        }

        return $found;
    }

    protected function pluginOf(string $class): string
    {
        return strtolower(explode('\\', ltrim($class, '\\'))[1] ?? '');
    }
}
