<?php namespace Aero\Mcp\Classes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Throwable;

/**
 * Convierte la declaración de un "recurso" (un modelo de tenant) en tools
 * MCP: `<clave>_list`, `<clave>_get` y, solo si declara `writable`,
 * `<clave>_create` y `<clave>_update`.
 *
 * El aislamiento NO depende de cada plugin: este motor impone siempre el
 * filtro por tenant, la lista blanca de campos y una lista negra de nombres
 * sensibles, aunque la declaración los pida.
 *
 *   'crm_contacts' => [
 *       'module'   => 'crm',                   // por defecto, el de la clave
 *       'plugin'   => 'Aero.Crm',              // plan del tenant
 *       'model'    => \Aero\Crm\Models\Contact::class,
 *       'label'    => 'contactos',
 *       'fields'   => 'id,first_name,email',   // lectura (CSV o array)
 *       'search'   => 'first_name,email',      // `query` hace LIKE en estas
 *       'filters'  => 'company_id,source',     // igualdad
 *       'order'    => ['id', 'desc'],
 *       'writable' => 'first_name,email',      // omitido = solo lectura
 *       'tenant_column' => 'tenant_id',        // por defecto
 *       'tenant_via'    => ['conversation_id', \Aero\X\Models\Conversation::class], // hijo sin tenant_id
 *   ]
 */
class McpResourceTools
{
    public const DEFAULT_LIMIT = 20;
    public const MAX_LIMIT = 50;
    public const MAX_TEXT = 4000;

    /** Nombres de columna que nunca salen ni se escriben por MCP. */
    protected const DENY = '/(token|secret|passw|hash|credential|api_?key|encrypted|^raw_|cached_|^auth$|private|_pin$|^pin$)/i';

    /** Columnas que ninguna escritura puede tocar. */
    protected const PROTECTED = ['id', 'created_at', 'updated_at', 'deleted_at'];

    public static function isDenied(string $column): bool
    {
        return (bool) preg_match(self::DENY, $column);
    }

    /** Declaración normalizada, o null si el modelo no existe (plugin ausente). */
    public static function normalize(string $key, array $def): ?array
    {
        $model = $def['model'] ?? null;

        if (!is_string($model) || !class_exists($model)) {
            return null;
        }

        $list = fn ($v) => is_array($v) ? array_values($v) : array_values(array_filter(array_map('trim', explode(',', (string) $v))));

        $tenantColumn = (string) ($def['tenant_column'] ?? 'tenant_id');
        $isChild = !empty($def['tenant_via']);

        $def['key'] = $key;
        $def['module'] = (string) ($def['module'] ?? strtok($key, '_'));
        $def['label'] = (string) ($def['label'] ?? $key);
        $def['tenant_column'] = $tenantColumn;
        $def['fields'] = array_values(array_filter($list($def['fields'] ?? 'id'), fn ($c) => !self::isDenied($c)));
        $def['search'] = array_values(array_filter($list($def['search'] ?? []), fn ($c) => !self::isDenied($c)));
        $def['filters'] = array_values(array_filter($list($def['filters'] ?? []), fn ($c) => !self::isDenied($c)));

        $writable = $isChild ? [] : $list($def['writable'] ?? []);
        $def['writable'] = array_values(array_filter(
            $writable,
            fn ($c) => !self::isDenied($c) && !in_array($c, array_merge(self::PROTECTED, [$tenantColumn]), true)
        ));

        return $def;
    }

    /** Tools generadas para un recurso ya normalizado. */
    public static function toolsFor(array $def): array
    {
        $key = $def['key'];
        $meta = ['module' => $def['module'], 'plugin' => $def['plugin'] ?? null];
        $fields = implode(', ', $def['fields']);

        $listProps = [
            'limit' => ['type' => 'integer', 'description' => 'Máximo de filas (1-' . self::MAX_LIMIT . ', por defecto ' . self::DEFAULT_LIMIT . ').'],
            'page'  => ['type' => 'integer', 'description' => 'Página, desde 1.'],
        ];
        $desc = "Lista {$def['label']} del tenant, más recientes primero. Campos devueltos: {$fields}.";

        if ($def['search']) {
            $listProps['query'] = ['type' => 'string', 'description' => 'Texto a buscar en: ' . implode(', ', $def['search']) . '.'];
        }
        if ($def['filters']) {
            $listProps['filters'] = [
                'type' => 'object',
                'description' => 'Igualdad exacta por: ' . implode(', ', $def['filters']) . '.',
                'properties' => array_fill_keys($def['filters'], new \stdClass()),
            ];
        }

        $tools = [
            "{$key}_list" => $meta + [
                'write' => false,
                'description' => $desc,
                'parameters' => ['type' => 'object', 'properties' => $listProps],
                'handler' => fn (array $args, int $tenantId) => self::list($def, $args, $tenantId),
            ],
            "{$key}_get" => $meta + [
                'write' => false,
                'description' => "Devuelve un registro de {$def['label']} por id. Campos: {$fields}.",
                'parameters' => [
                    'type' => 'object',
                    'properties' => ['id' => ['type' => 'integer']],
                    'required' => ['id'],
                ],
                'handler' => fn (array $args, int $tenantId) => self::get($def, $args, $tenantId),
            ],
        ];

        if ($def['writable']) {
            $writeProps = array_fill_keys($def['writable'], new \stdClass());
            $writeList = implode(', ', $def['writable']);

            $tools["{$key}_create"] = $meta + [
                'write' => true,
                'description' => "Crea un registro de {$def['label']}. Campos aceptados: {$writeList}.",
                'parameters' => ['type' => 'object', 'properties' => $writeProps],
                'handler' => fn (array $args, int $tenantId) => self::create($def, $args, $tenantId),
            ];
            $tools["{$key}_update"] = $meta + [
                'write' => true,
                'description' => "Modifica un registro de {$def['label']} por id. Solo cambia los campos enviados: {$writeList}.",
                'parameters' => [
                    'type' => 'object',
                    'properties' => ['id' => ['type' => 'integer']] + $writeProps,
                    'required' => ['id'],
                ],
                'handler' => fn (array $args, int $tenantId) => self::update($def, $args, $tenantId),
            ];
        }

        return $tools;
    }

    // ---- operaciones ----

    public static function list(array $def, array $args, int $tenantId): array
    {
        $table = (new $def['model'])->getTable();
        $query = self::scoped($def, $tenantId);

        if ($def['search'] && ($text = trim((string) ($args['query'] ?? ''))) !== '') {
            $like = '%' . addcslashes($text, '%_\\') . '%';
            $query->where(function (Builder $q) use ($def, $table, $like) {
                foreach ($def['search'] as $column) {
                    $q->orWhere("{$table}.{$column}", 'like', $like);
                }
            });
        }

        foreach ((array) ($args['filters'] ?? []) as $column => $value) {
            if (in_array($column, $def['filters'], true) && (is_scalar($value) || $value === null)) {
                $value === null
                    ? $query->whereNull("{$table}.{$column}")
                    : $query->where("{$table}.{$column}", $value);
            }
        }

        $limit = max(1, min(self::MAX_LIMIT, (int) ($args['limit'] ?? self::DEFAULT_LIMIT)));
        $page = max(1, (int) ($args['page'] ?? 1));
        $total = (clone $query)->toBase()->getCountForPagination();

        [$orderColumn, $direction] = $def['order'] ?? [(new $def['model'])->getKeyName(), 'desc'];
        $rows = $query->orderBy("{$table}.{$orderColumn}", strtolower($direction) === 'asc' ? 'asc' : 'desc')
            ->forPage($page, $limit)->get();

        return [
            'data'     => $rows->map(fn ($row) => self::present($def, $row))->all(),
            'total'    => $total,
            'page'     => $page,
            'per_page' => $limit,
            'has_more' => $page * $limit < $total,
        ];
    }

    public static function get(array $def, array $args, int $tenantId): array
    {
        return self::present($def, self::find($def, $args, $tenantId));
    }

    public static function create(array $def, array $args, int $tenantId): array
    {
        $data = Arr::only($args, $def['writable']);

        if (!$data) {
            throw new ToolError('No se envió ningún campo válido. Aceptados: ' . implode(', ', $def['writable']) . '.');
        }

        $model = new $def['model'];
        $model->forceFill($data);
        $model->setAttribute($def['tenant_column'], $tenantId);

        self::save($model);

        return self::present($def, $model->fresh() ?? $model);
    }

    public static function update(array $def, array $args, int $tenantId): array
    {
        $model = self::find($def, $args, $tenantId);
        $data = Arr::only($args, $def['writable']);

        if (!$data) {
            throw new ToolError('No se envió ningún campo modificable. Aceptados: ' . implode(', ', $def['writable']) . '.');
        }

        $model->forceFill($data);
        self::save($model);

        return self::present($def, $model->fresh() ?? $model);
    }

    // ---- internos ----

    /** Consulta del modelo ya limitada al tenant. Falla cerrado. */
    protected static function scoped(array $def, int $tenantId): Builder
    {
        if ($tenantId <= 0) {
            throw new ToolError('Tenant inválido.');
        }

        $model = new $def['model'];
        $table = $model->getTable();
        $query = $model->newQuery();

        if (!empty($def['tenant_via'])) {
            [$foreignKey, $parentClass] = $def['tenant_via'];
            $parent = new $parentClass;

            return $query->whereIn(
                "{$table}.{$foreignKey}",
                $parent->newQuery()->where($parent->getTable() . '.tenant_id', $tenantId)->select($parent->getQualifiedKeyName())
            );
        }

        return $query->where("{$table}.{$def['tenant_column']}", $tenantId);
    }

    protected static function find(array $def, array $args, int $tenantId)
    {
        $id = $args['id'] ?? null;

        if (!is_numeric($id)) {
            throw new ToolError('Falta el id.');
        }

        $model = (new $def['model']);
        $row = self::scoped($def, $tenantId)->where($model->getQualifiedKeyName(), (int) $id)->first();

        if (!$row) {
            throw new ToolError("No se encontró {$def['label']} con id {$id}.");
        }

        return $row;
    }

    protected static function save($model): void
    {
        try {
            $model->save();
        } catch (\October\Rain\Exception\ValidationException $e) {
            throw new ToolError('Validación: ' . implode(' ', $e->getErrors()->all()));
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw new ToolError('Validación: ' . implode(' ', Arr::flatten($e->errors())));
        }
    }

    protected static function present(array $def, $row): array
    {
        $out = [];

        foreach ($def['fields'] as $field) {
            $value = $row->getAttribute($field);

            if ($value instanceof \DateTimeInterface) {
                $value = $value->format(\DateTimeInterface::ATOM);
            } elseif (is_object($value)) {
                $value = method_exists($value, 'toArray') ? $value->toArray() : (string) $value;
            } elseif (is_string($value) && mb_strlen($value) > self::MAX_TEXT) {
                $value = mb_substr($value, 0, self::MAX_TEXT) . '…';
            }

            $out[$field] = $value;
        }

        return $out;
    }
}
