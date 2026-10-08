<?php namespace Aero\Sheets\Classes;

use Event;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Support\Facades\Schema;

/**
 * Qué modelos pueden ofrecerse como fuente y qué campos se pueden proponer al
 * superadmin. Los plugins se anuncian por evento (sugerencia, no obligatorio):
 *   Event::listen('aero.sheets.registerModels', fn () => [[
 *       'model' => \Aero\Crm\Models\Contact::class, 'label' => 'Contactos CRM',
 *   ]]);
 * Lo que de verdad se sincroniza es solo lo que el superadmin marque en la fuente.
 */
class SourceRegistry
{
    /** Columnas que jamás se ofrecen (credenciales y similares). */
    const DENY = '/(password|passwd|secret|token|api_?key|persist_code|activation_code|reset_password_code|remember|signature)/i';

    /** Administrados por el sistema: nunca se escriben desde una hoja. */
    const READONLY = ['id', 'tenant_id', 'created_at', 'updated_at', 'deleted_at'];

    public static function suggestions(): array
    {
        $out = [];
        foreach (array_filter(Event::dispatch('aero.sheets.registerModels') ?: []) as $list) {
            foreach ((array) $list as $item) {
                if (!empty($item['model']) && self::isAllowedClass($item['model'])) {
                    $out[$item['model']] = $item['label'] ?? class_basename($item['model']);
                }
            }
        }

        return $out;
    }

    /** Solo modelos de plugins Aero: evita apuntar a User, Backend, etc. */
    public static function isAllowedClass(?string $class): bool
    {
        return $class
            && preg_match('/^Aero\\\\[A-Za-z0-9]+\\\\Models\\\\[A-Za-z0-9\\\\]+$/', $class) === 1
            && class_exists($class)
            && is_subclass_of($class, EloquentModel::class);
    }

    public static function table(string $class): ?string
    {
        return self::isAllowedClass($class) ? (new $class)->getTable() : null;
    }

    public static function hasTenantColumn(?string $class): bool
    {
        return ($t = $class ? self::table($class) : null) && Schema::hasColumn($t, 'tenant_id');
    }

    /** Campos candidatos (de la tabla), sin los vetados. */
    public static function candidateFields(string $class): array
    {
        $table = self::table($class);
        if (!$table) {
            return [];
        }

        $out = [];
        foreach (Schema::getColumns($table) as $col) {
            $name = $col['name'];
            if (preg_match(self::DENY, $name)) {
                continue;
            }
            $out[$name] = [
                'key'      => $name,
                'label'    => ucfirst(str_replace('_', ' ', $name)),
                'type'     => self::kind($col['type_name'] ?? $col['type'] ?? ''),
                'readonly' => in_array($name, self::READONLY, true),
            ];
        }

        return $out;
    }

    protected static function kind(string $type): string
    {
        $type = strtolower($type);

        return match (true) {
            str_contains($type, 'tinyint(1)'), $type === 'boolean', $type === 'bool' => 'bool',
            str_contains($type, 'int') => 'int',
            str_contains($type, 'decimal'), str_contains($type, 'float'), str_contains($type, 'double'), str_contains($type, 'numeric') => 'decimal',
            $type === 'date' => 'date',
            str_contains($type, 'timestamp'), str_contains($type, 'datetime') => 'datetime',
            str_contains($type, 'json') => 'json',
            default => 'string',
        };
    }
}
