<?php namespace Aero\Workflows\Classes;

use Aero\Workflows\Models\Workflow;
use Illuminate\Support\Str;

/**
 * Exportar / importar workflows como JSON.
 *
 * Reglas de seguridad (un archivo de terceros es un riesgo, uno propio se filtra):
 *  - Al EXPORTAR no viaja nada ligado al tenant de origen (ids de cuentas,
 *    Connectors, categorías, productos…) ni secretos (secreto del webhook,
 *    tokens, contraseñas): se quitan y se listan en `reconnect`.
 *  - Al IMPORTAR el workflow nace siempre como BORRADOR, DESACTIVADO y sin
 *    «ofrecer a la IA», en el tenant de quien importa. Una persona lo revisa,
 *    reconecta lo marcado y lo publica.
 *  - Un nodo que este sistema no conoce, o un archivo mal formado, se rechaza.
 */
class WorkflowPorter
{
    public const FORMAT = 'aero.workflow';
    public const BUNDLE = 'aero.workflow-bundle';
    public const VERSION = 1;
    public const MAX_BYTES = 1048576;
    public const MAX_WORKFLOWS = 20;

    /** Campos de un nodo que apuntan a datos de UN tenant: no se exportan. */
    public const BOUND = [
        'account_id'         => 'cuenta de WhatsApp',
        'connector_id'       => 'Connector',
        'category_id'        => 'categoría de la tienda',
        'product_id'         => 'producto de la tienda',
        'payment_gateway_id' => 'método de pago',
    ];

    public const TRIGGERS = ['manual', 'event', 'message', 'webhook'];

    protected const SECRET_KEY = '/^(authorization|x-api-key|api[_-]?key|apikey|(client_|webhook_)?secret|password|passwd|bearer|(access_|auth_|refresh_|bearer_|api_)?token)$/i';

    /** @return array{format:string, version:int, exported_at:string, workflow:array, requires:string[], reconnect:array} */
    public static function export(Workflow $workflow): array
    {
        $graph = $workflow->jsonField('graph');
        $config = $workflow->jsonField('trigger_config');
        $reconnect = [];
        $registry = NodeRegistry::all();
        $requires = [];

        foreach ((array) ($graph['nodes'] ?? []) as $i => $node) {
            $type = (string) ($node['type'] ?? '');
            $label = $registry[$type]['label'] ?? $type;
            $requires[$type] = true;

            $graph['nodes'][$i]['data'] = static::scrub((array) ($node['data'] ?? []), (string) ($node['id'] ?? ''), $label, $reconnect);
        }

        if (!empty($config['account_id'])) {
            unset($config['account_id']);
            $reconnect[] = ['node' => null, 'where' => 'Disparador', 'field' => 'account_id', 'what' => 'Cuenta de WhatsApp que lo dispara (opcional: sin ella responde a cualquiera).'];
        }

        if (!empty($config['secret'])) {
            unset($config['secret']);
            $reconnect[] = ['node' => null, 'where' => 'Disparador', 'field' => 'secret', 'what' => 'Secreto del webhook: define uno nuevo.'];
        }

        return [
            'format'      => static::FORMAT,
            'version'     => static::VERSION,
            'exported_at' => now()->toIso8601String(),
            'workflow'    => [
                'name'             => (string) $workflow->name,
                'slug'             => (string) $workflow->slug,
                'description'      => (string) $workflow->description,
                'trigger_type'     => (string) $workflow->trigger_type,
                'trigger_config'   => $config,
                'graph'            => $graph,
                'tool_description' => $workflow->tool_description,
                'tool_schema'      => $workflow->jsonField('tool_schema') ?: null,
            ],
            'requires'    => array_keys($requires),
            'reconnect'   => $reconnect,
        ];
    }

    public static function bundle(array $exports): array
    {
        return [
            'format'      => static::BUNDLE,
            'version'     => static::VERSION,
            'exported_at' => now()->toIso8601String(),
            'workflows'   => array_values($exports),
        ];
    }

    /**
     * Texto → lista de exportaciones (una o varias).
     *
     * @throws \InvalidArgumentException con un mensaje apto para mostrar tal cual
     */
    public static function parse(string $json): array
    {
        $json = trim($json);

        if ($json === '') {
            throw new \InvalidArgumentException('Sube un archivo .json o pega su contenido.');
        }

        if (strlen($json) > static::MAX_BYTES) {
            throw new \InvalidArgumentException('El archivo es demasiado grande (máximo 1 MB).');
        }

        $data = json_decode($json, true);

        if (!is_array($data)) {
            throw new \InvalidArgumentException('El archivo no es un JSON válido.');
        }

        $format = $data['format'] ?? null;

        if (!in_array($format, [static::FORMAT, static::BUNDLE], true)) {
            throw new \InvalidArgumentException('Este archivo no es un workflow exportado desde aquí.');
        }

        if ((int) ($data['version'] ?? 0) < 1 || (int) $data['version'] > static::VERSION) {
            throw new \InvalidArgumentException('Este archivo es de una versión del formato que este sistema no conoce.');
        }

        $items = $format === static::BUNDLE ? (array) ($data['workflows'] ?? []) : [$data];

        if (!$items) {
            throw new \InvalidArgumentException('El archivo no trae ningún workflow.');
        }

        if (count($items) > static::MAX_WORKFLOWS) {
            throw new \InvalidArgumentException('Máximo ' . static::MAX_WORKFLOWS . ' workflows por archivo.');
        }

        return array_values($items);
    }

    /**
     * Crea UN workflow (borrador, desactivado) para el tenant dado.
     *
     * @return array{workflow: Workflow, reconnect: array, warnings: string[]}
     * @throws \InvalidArgumentException
     */
    public static function import(array $item, ?int $tenantId): array
    {
        $in = (array) ($item['workflow'] ?? []);
        $name = trim((string) ($in['name'] ?? ''));
        $graph = $in['graph'] ?? null;

        if ($name === '' || !is_array($graph)) {
            throw new \InvalidArgumentException('Falta el nombre o el diseño del workflow.');
        }

        if ($errors = GraphValidator::integrityErrors($graph)) {
            throw new \InvalidArgumentException("«{$name}»: " . implode(' ', array_slice($errors, 0, 3)));
        }

        $trigger = in_array($in['trigger_type'] ?? '', static::TRIGGERS, true) ? $in['trigger_type'] : 'manual';
        $config = is_array($in['trigger_config'] ?? null) ? $in['trigger_config'] : [];
        unset($config['secret'], $config['account_id']);

        // Lo que el archivo ya traía para reconectar, más lo que el archivo quiso colar (se quita igual).
        $reconnect = [];
        foreach ((array) $graph['nodes'] as $i => $node) {
            $label = NodeRegistry::all()[$node['type']]['label'] ?? $node['type'];
            $graph['nodes'][$i]['data'] = static::scrub((array) $node['data'], (string) $node['id'], $label, $reconnect);
        }
        $reconnect = array_merge(
            array_values(array_filter((array) ($item['reconnect'] ?? []), fn ($r) => is_array($r) && !empty($r['what']))),
            $reconnect
        );

        $warnings = GraphValidator::validate($graph);

        $notes = array_map(fn ($r) => '• ' . ($r['where'] ?? 'Nodo') . ': ' . $r['what'], $reconnect);
        $description = trim((string) ($in['description'] ?? ''));

        if ($notes) {
            $description = trim($description . "\n\n⚠ Pendiente tras importar:\n" . implode("\n", $notes));
        }

        $workflow = Workflow::create([
            'tenant_id'        => $tenantId,
            'name'             => mb_substr($name, 0, 190),
            'slug'             => static::uniqueSlug((string) ($in['slug'] ?? '') ?: $name, $tenantId),
            'description'      => $description ?: null,
            'is_active'        => false,
            'status'           => 'draft',
            'trigger_type'     => $trigger,
            'trigger_config'   => $config ?: null,
            'graph'            => json_encode($graph, JSON_UNESCAPED_UNICODE),
            'expose_as_tool'   => false,
            'tool_description' => isset($in['tool_description']) ? (string) $in['tool_description'] : null,
            'tool_schema'      => is_array($in['tool_schema'] ?? null) ? json_encode($in['tool_schema'], JSON_UNESCAPED_UNICODE) : null,
        ]);

        return ['workflow' => $workflow, 'reconnect' => $reconnect, 'warnings' => $warnings];
    }

    /**
     * Quita de los datos de un nodo lo ligado al tenant y los secretos.
     * Una plantilla {{ … }} no es un id fijo: se conserva.
     */
    protected static function scrub(array $data, string $nodeId, string $label, array &$reconnect, string $path = ''): array
    {
        foreach ($data as $key => $value) {
            $here = $path === '' ? (string) $key : "{$path}.{$key}";

            if (is_array($value)) {
                $data[$key] = static::scrub($value, $nodeId, $label, $reconnect, $here);
                continue;
            }

            $filled = $value !== null && $value !== '' && !(is_string($value) && str_contains($value, '{{'));

            if (!$filled) {
                continue;
            }

            if (is_string($key) && isset(static::BOUND[$key])) {
                unset($data[$key]);
                $reconnect[] = ['node' => $nodeId, 'where' => "{$label} ({$nodeId})", 'field' => $here, 'what' => 'Elige de nuevo: ' . static::BOUND[$key] . '.'];
            }
            elseif (is_string($key) && preg_match(static::SECRET_KEY, $key)) {
                unset($data[$key]);
                $reconnect[] = ['node' => $nodeId, 'where' => "{$label} ({$nodeId})", 'field' => $here, 'what' => "Vuelve a poner la credencial «{$here}» (no se exporta)."];
            }
        }

        return $data;
    }

    public static function uniqueSlug(string $base, ?int $tenantId): string
    {
        $slug = Str::slug($base) ?: 'workflow';
        $candidate = $slug;
        $n = 2;

        while (Workflow::where('slug', $candidate)->where('tenant_id', $tenantId)->exists()) {
            $candidate = $slug . '-' . $n++;
        }

        return $candidate;
    }
}
