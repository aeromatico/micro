<?php namespace Aero\Workflows\Classes;

use Aero\Workflows\Models\Workflow;

/**
 * Herramientas (AI tools) para que un agente DISEÑE workflows de verdad:
 * conocer el catálogo vivo de nodos, ver el contexto del tenant, validar un
 * grafo con las mismas reglas del editor y dejarlo como BORRADOR.
 *
 * Se registran en `aero.chatbots.registerAiTools` (Plugin::boot) bajo la
 * categoría `workflows_builder`, así las usan el Super Chatbot IA y el servidor
 * MCP (con el scope `mcp.tool.<nombre>`).
 *
 * Reglas duras (no dependen de lo que diga el modelo):
 *  - El tenant es SIEMPRE el que resuelve el motor (segundo argumento del
 *    handler); nunca uno que venga en los argumentos.
 *  - Lo guardado es siempre BORRADOR, desactivado y sin «ofrecer como herramienta».
 *    Publicar y activar lo hace una persona desde el editor. Un workflow
 *    publicado no se modifica por aquí.
 *  - Un grafo con errores no se guarda: se devuelven los errores para corregir.
 *  - Las cuentas y Connectors que referencie deben ser del tenant.
 *  - Guardar exige el plan acordado con la persona (`agreed_plan`): el agente
 *    solo construye después de ponerse de acuerdo.
 */
class BuilderTools
{
    public const CATEGORY = 'workflows_builder';
    public const MAX_DRAFTS_PER_TENANT = 50;

    /** Qué significa cada tipo de disparador y qué configuración admite. */
    public const TRIGGERS = [
        'manual'  => 'Lo lanza una persona (botón Probar) o la IA como herramienta. Sin configuración.',
        'message' => 'Un cliente escribe por WhatsApp. Config opcional: keyword (texto que debe contener), account_id (solo esa cuenta), interactive_id (solo si tocó ese botón/opción; texto o lista).',
        'event'   => 'Pasa algo en la plataforma. Config: event (nombre aero.*, admite comodín *), p. ej. {"event":"aero.shop.orderCreated"}.',
        'webhook' => 'Un sistema externo llama con una petición firmada. Config: secret (lo define una persona; no se guarda desde aquí).',
    ];

    public static function tools(): array
    {
        $graph = [
            'type' => 'object',
            'description' => 'Grafo del flujo: {"nodes":[{"id","type","data"}],"edges":[{"source","target","sourceHandle"?}]}. Un solo disparador, sin ciclos, todas las salidas conectadas.',
            'properties' => [
                'nodes' => ['type' => 'array', 'items' => ['type' => 'object']],
                'edges' => ['type' => 'array', 'items' => ['type' => 'object']],
            ],
            'required' => ['nodes', 'edges'],
        ];

        $trigger = [
            'trigger_type'   => ['type' => 'string', 'enum' => array_keys(static::TRIGGERS), 'description' => 'Debe coincidir con el nodo disparador del grafo (trigger.<tipo>).'],
            'trigger_config' => ['type' => 'object', 'description' => 'Configuración del disparador (ver workflows_context → triggers).'],
        ];

        $tool = fn (string $description, array $properties, array $required, string $method) => [
            'description' => $description,
            'category'    => static::CATEGORY,
            'parameters'  => ['type' => 'object', 'properties' => $properties ?: new \stdClass(), 'required' => $required],
            'handler'     => [static::class, $method],
        ];

        return [
            'workflows_context' => $tool(
                'Primero, siempre. Devuelve lo que el cliente tiene a mano para diseñar: sus cuentas de WhatsApp (y si admiten botones/menús), Connectors, módulos instalados, cuántos workflows tiene, los límites del motor, los tipos de disparador y la sintaxis de plantillas.',
                [], [], 'context'
            ),
            'workflows_catalog' => $tool(
                'Catálogo VIVO de nodos. Sin argumentos devuelve la lista corta (tipo, nombre, categoría, salidas, si tiene efectos). Con `types` devuelve el detalle completo (campos, límites, salidas, notas) de esos nodos: pídelo antes de armar cada nodo. Nunca uses un nodo, campo o salida que no aparezca aquí.',
                ['types' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Tipos de nodo de los que quieres el detalle, p. ej. ["logic.condition","hello.reply_buttons"].']],
                [], 'catalog'
            ),
            'workflows_list' => $tool(
                'Lista los workflows que el cliente ya tiene (id, nombre, disparador, estado, tipos de nodo). Úsalo para no duplicar lo que ya existe y para proponer reutilizarlo o ampliarlo.',
                [], [], 'listing'
            ),
            'workflows_get' => $tool(
                'Devuelve el diseño completo (grafo, disparador, descripción) de UN workflow del cliente, para entenderlo o continuarlo.',
                ['id' => ['type' => 'integer', 'description' => 'Id del workflow.']],
                ['id'], 'get'
            ),
            'workflows_validate' => $tool(
                'Valida un diseño con las mismas reglas del editor (estructura, salidas conectadas, ciclos, límites de cada nodo, cuentas y Connectors del cliente). No guarda nada. Devuelve {valid, errors[], warnings[]}. Úsalo hasta que `valid` sea true.',
                ['graph' => $graph] + $trigger,
                ['graph', 'trigger_type'], 'validate'
            ),
            'workflows_save_draft' => $tool(
                'Guarda el diseño como BORRADOR desactivado para que una persona lo revise y publique. SOLO después de que la persona haya confirmado el plan con sus palabras. Si es válido devuelve el id y el enlace del editor; si no, devuelve los errores y no guarda nada. Con `workflow_id` reemplaza un borrador existente (nunca uno publicado).',
                [
                    'name'             => ['type' => 'string', 'description' => 'Nombre claro, hasta 190 caracteres.'],
                    'description'      => ['type' => 'string', 'description' => 'Qué hace el flujo, en una o dos frases.'],
                    'agreed_plan'      => ['type' => 'string', 'description' => 'El plan que la persona aprobó, en pasos simples. Obligatorio.'],
                    'graph'            => $graph,
                    'workflow_id'      => ['type' => 'integer', 'description' => 'Opcional: id de un BORRADOR a reemplazar.'],
                    'tool_description' => ['type' => 'string', 'description' => 'Opcional, solo trigger manual: cuándo debe usarlo la IA.'],
                    'tool_schema'      => ['type' => 'object', 'description' => 'Opcional, solo trigger manual: JSON Schema de los parámetros que recibe.'],
                ] + $trigger,
                ['name', 'agreed_plan', 'graph', 'trigger_type'], 'saveDraft'
            ),
        ];
    }

    // ---------------------------------------------------------------- contexto

    public static function context(array $args, int $tenantId): array
    {
        $accounts = [];
        $connectors = [];

        // Dependencias blandas: si Hello/Connector no están (o sin tablas) el contexto sigue, solo sin esos datos.
        try {
            if (class_exists(\Aero\Hello\Models\Account::class)) {
                foreach (\Aero\Hello\Models\Account::forTenant($tenantId)->enabled()->ofPlatform('whatsapp')->orderBy('label')->get() as $a) {
                    $accounts[] = [
                        'id'                     => $a->id,
                        'label'                  => $a->label,
                        'driver'                 => $a->driver,
                        'connected'              => $a->status === 'connected',
                        'supports_buttons_menus' => $a->can('buttons'),
                    ];
                }
            }
        }
        catch (\Throwable) {
            $accounts = [];
        }

        try {
            if (class_exists(\Aero\Connector\Models\Connector::class) && class_exists(\Aero\Sites\Models\Tenant::class)) {
                $connectors = \Aero\Connector\Models\Connector::where('owner_type', \Aero\Sites\Models\Tenant::class)
                    ->where('owner_id', $tenantId)->where('is_enabled', true)->orderBy('name')->get(['id', 'name'])->toArray();
            }
        }
        catch (\Throwable) {
            $connectors = [];
        }

        $events = class_exists(\Aero\Notify\Classes\EventCatalog::class) ? \Aero\Notify\Classes\EventCatalog::codes() : [];

        return [
            'whatsapp_accounts'  => $accounts,
            'connectors'         => $connectors,
            'modules'            => [
                'hello' => class_exists(\Aero\Hello\Classes\Hello::class),
                'shop'  => class_exists(\Aero\Shop\Models\Product::class),
                'crm'   => class_exists(\Aero\Crm\Models\Contact::class),
                'notify' => class_exists(\Aero\Notify\Classes\Notify::class),
            ],
            'workflows_count'    => Workflow::where('tenant_id', $tenantId)->count(),
            'drafts_left'        => max(0, static::MAX_DRAFTS_PER_TENANT - Workflow::where('tenant_id', $tenantId)->where('status', 'draft')->count()),
            'limits'             => [
                'max_nodes'            => GraphValidator::MAX_NODES,
                'max_steps_per_run'    => WorkflowRunner::MAX_STEPS,
                'max_seconds_per_run'  => WorkflowRunner::MAX_SECONDS,
                'max_wait_seconds'     => 86400,
                'max_runs_per_hour'    => WorkflowRunner::MAX_RUNS_PER_HOUR,
                'one_trigger'          => true,
                'cycles_allowed'       => false,
                'every_output_must_be_connected' => true,
            ],
            'triggers'           => static::TRIGGERS,
            'known_events'       => $events,
            'templates'          => 'Solo rutas con puntos, sin cálculos: {{ trigger.campo }}, {{ vars.nombre }}, {{ nodes.<id>.campo }}. Un campo que es SOLO un placeholder conserva su tipo. Mensaje entrante: {{ trigger.data.0.body }}, {{ trigger.data.0.provider_payload.interactive_id }}.',
            'whatsapp_rules'     => 'Botones/menú/enlace/pedir ubicación/llamada: solo con cuenta de API oficial, solo dentro de las 24 h del último mensaje del cliente, solo responden al remitente. El flujo NO espera la respuesta: la opción tocada llega como un mensaje nuevo (provider_payload.interactive_id) y se atiende con OTRO workflow de disparador message + interactive_id, o con una Condición.',
            'publishing'         => 'Todo se guarda como borrador desactivado. Una persona lo revisa, prueba con «Probar» y lo publica.',
        ];
    }

    // ---------------------------------------------------------------- catálogo

    public static function catalog(array $args, int $tenantId): array
    {
        $registry = NodeRegistry::all();
        $types = array_values(array_filter((array) ($args['types'] ?? []), 'is_string'));

        if (!$types) {
            $list = [];

            foreach ($registry as $type => $def) {
                $list[] = [
                    'type'         => $type,
                    'label'        => $def['label'] ?? $type,
                    'category'     => $def['category'] ?? 'action',
                    'outputs'      => array_column((array) ($def['handles'] ?? []), 'id') ?: ['(una sola)'],
                    'has_effects'  => in_array($type, GraphValidator::SIDE_EFFECT_TYPES, true),
                ];
            }

            return ['nodes' => $list, 'hint' => 'Pide el detalle de los que vayas a usar con {"types":[...]}.'];
        }

        $detail = [];
        $unknown = [];

        foreach ($types as $type) {
            $def = $registry[$type] ?? null;

            if (!$def) {
                $unknown[] = $type;
                continue;
            }

            $detail[$type] = [
                'label'       => $def['label'] ?? $type,
                'category'    => $def['category'] ?? 'action',
                'has_effects' => in_array($type, GraphValidator::SIDE_EFFECT_TYPES, true),
                'outputs'     => $def['handles'] ?? [],
                'note'        => $def['note'] ?? null,
                'fields'      => $def['fields'] ?? [],
            ];
        }

        return array_filter(['nodes' => $detail, 'unknown_types' => $unknown]);
    }

    // ---------------------------------------------------------------- lectura

    public static function listing(array $args, int $tenantId): array
    {
        $rows = Workflow::where('tenant_id', $tenantId)->orderByDesc('id')->limit(100)->get()->map(function ($w) {
            $graph = $w->jsonField('graph');

            return [
                'id'           => $w->id,
                'name'         => $w->name,
                'status'       => $w->status,
                'active'       => (bool) $w->is_active,
                'trigger_type' => $w->trigger_type,
                'trigger_config' => $w->jsonField('trigger_config'),
                'node_types'   => array_values(array_unique(array_column((array) ($graph['nodes'] ?? []), 'type'))),
                'as_ai_tool'   => (bool) $w->expose_as_tool,
            ];
        })->all();

        return ['workflows' => $rows];
    }

    public static function get(array $args, int $tenantId): array
    {
        $w = Workflow::where('tenant_id', $tenantId)->find((int) ($args['id'] ?? 0));

        if (!$w) {
            return ['error' => 'No existe ese workflow en este cliente.'];
        }

        return [
            'id' => $w->id, 'name' => $w->name, 'description' => $w->description, 'status' => $w->status, 'active' => (bool) $w->is_active,
            'trigger_type' => $w->trigger_type, 'trigger_config' => $w->jsonField('trigger_config'), 'graph' => $w->jsonField('graph'),
        ];
    }

    // -------------------------------------------------------------- validación

    public static function validate(array $args, int $tenantId): array
    {
        [$errors, $warnings] = static::check($args, $tenantId);

        return ['valid' => !$errors, 'errors' => $errors, 'warnings' => $warnings];
    }

    /** @return array{0: string[], 1: string[]} errores y avisos */
    protected static function check(array $args, int $tenantId): array
    {
        $graph = is_array($args['graph'] ?? null) ? $args['graph'] : [];
        $type = (string) ($args['trigger_type'] ?? '');
        $config = is_array($args['trigger_config'] ?? null) ? $args['trigger_config'] : [];
        $errors = [];
        $warnings = [];

        if (!isset(static::TRIGGERS[$type])) {
            $errors[] = 'trigger_type debe ser uno de: ' . implode(', ', array_keys(static::TRIGGERS)) . '.';
        }

        $errors = array_merge($errors, GraphValidator::integrityErrors($graph));

        if (!$errors) {
            $errors = array_merge($errors, GraphValidator::validate($graph));
        }

        // El disparador del grafo debe ser el del tipo declarado.
        foreach ((array) ($graph['nodes'] ?? []) as $node) {
            if (str_starts_with((string) ($node['type'] ?? ''), 'trigger.') && $type !== '' && $node['type'] !== "trigger.{$type}") {
                $errors[] = "El nodo disparador es «{$node['type']}» pero trigger_type es «{$type}»: deben coincidir.";
            }
        }

        if ($type === 'event' && trim((string) ($config['event'] ?? '')) === '') {
            $errors[] = 'El disparador «event» necesita trigger_config.event (p. ej. "aero.shop.orderCreated").';
        }

        if ($type === 'webhook') {
            $warnings[] = 'El webhook necesita un secreto: una persona debe definirlo en el editor antes de publicar.';
        }

        if ($type === 'message' && !empty($config['account_id']) && !static::ownsAccount((int) $config['account_id'], $tenantId)) {
            $errors[] = 'trigger_config.account_id no es una cuenta de este cliente.';
        }

        foreach ((array) ($graph['nodes'] ?? []) as $node) {
            $data = is_array($node['data'] ?? null) ? $node['data'] : [];
            $id = $node['id'] ?? '?';

            if (!empty($data['account_id']) && is_numeric($data['account_id']) && !static::ownsAccount((int) $data['account_id'], $tenantId)) {
                $errors[] = "El nodo «{$id}» usa una cuenta (account_id) que no es de este cliente.";
            }

            if (!empty($data['connector_id']) && is_numeric($data['connector_id']) && !static::ownsConnector((int) $data['connector_id'], $tenantId)) {
                $errors[] = "El nodo «{$id}» usa un Connector (connector_id) que no es de este cliente.";
            }

            if (str_starts_with((string) ($node['type'] ?? ''), 'hello.reply_') && $type !== 'message') {
                $warnings[] = "El nodo «{$id}» responde al remitente de un mensaje: sin disparador «message» solo funciona en modo prueba (no envía).";
            }

            if (in_array($node['type'] ?? '', GraphValidator::SIDE_EFFECT_TYPES, true)) {
                $warnings[] = "El nodo «{$id}» ({$node['type']}) tiene efectos reales al ejecutarse: revísalo antes de publicar.";
            }
        }

        return [array_values(array_unique($errors)), array_values(array_unique($warnings))];
    }

    protected static function ownsAccount(int $id, int $tenantId): bool
    {
        try {
            return class_exists(\Aero\Hello\Models\Account::class)
                && \Aero\Hello\Models\Account::forTenant($tenantId)->where('id', $id)->exists();
        }
        catch (\Throwable) {
            return false; // falla cerrado
        }
    }

    protected static function ownsConnector(int $id, int $tenantId): bool
    {
        try {
            return class_exists(\Aero\Connector\Models\Connector::class) && class_exists(\Aero\Sites\Models\Tenant::class)
                && \Aero\Connector\Models\Connector::where('id', $id)->where('owner_type', \Aero\Sites\Models\Tenant::class)->where('owner_id', $tenantId)->exists();
        }
        catch (\Throwable) {
            return false; // falla cerrado
        }
    }

    // ---------------------------------------------------------------- guardado

    public static function saveDraft(array $args, int $tenantId): array
    {
        if ($tenantId <= 0) {
            return ['saved' => false, 'errors' => ['No se pudo determinar el cliente.']];
        }

        $name = trim((string) ($args['name'] ?? ''));
        $plan = trim((string) ($args['agreed_plan'] ?? ''));

        if ($name === '') {
            return ['saved' => false, 'errors' => ['Falta el nombre del workflow.']];
        }

        if (mb_strlen($plan) < 20) {
            return ['saved' => false, 'errors' => ['Falta agreed_plan: antes de guardar, acuerda el plan con la persona y envíalo aquí en pasos simples.']];
        }

        [$errors, $warnings] = static::check($args, $tenantId);

        if ($errors) {
            return ['saved' => false, 'errors' => $errors, 'warnings' => $warnings];
        }

        $existing = null;

        if (!empty($args['workflow_id'])) {
            $existing = Workflow::where('tenant_id', $tenantId)->find((int) $args['workflow_id']);

            if (!$existing) {
                return ['saved' => false, 'errors' => ['Ese workflow no existe en este cliente.']];
            }

            if ($existing->status !== 'draft') {
                return ['saved' => false, 'errors' => ['Solo se pueden reemplazar borradores. Ese workflow ya está publicado: crea uno nuevo y que una persona decida cuál conservar.']];
            }
        }
        elseif (Workflow::where('tenant_id', $tenantId)->where('status', 'draft')->count() >= static::MAX_DRAFTS_PER_TENANT) {
            return ['saved' => false, 'errors' => ['Hay demasiados borradores (' . static::MAX_DRAFTS_PER_TENANT . '). Una persona debe eliminar algunos primero.']];
        }

        $graph = $args['graph'];

        // Sin posiciones el editor apilaría los nodos: se acomodan por capas.
        if (array_filter((array) $graph['nodes'], fn ($n) => !isset($n['position']))) {
            $graph = GraphLayout::apply($graph);
        }

        $description = trim((string) ($args['description'] ?? '')) . "\n\nPlan acordado:\n" . $plan;

        $attributes = [
            'name'             => mb_substr($name, 0, 190),
            'description'      => trim($description),
            'is_active'        => false,
            'status'           => 'draft',
            'trigger_type'     => $args['trigger_type'],
            'trigger_config'   => ($config = static::cleanConfig($args)) ? json_encode($config, JSON_UNESCAPED_UNICODE) : null,
            'graph'            => json_encode($graph, JSON_UNESCAPED_UNICODE),
            'expose_as_tool'   => false,
            'tool_description' => ($args['trigger_type'] === 'manual' && !empty($args['tool_description'])) ? (string) $args['tool_description'] : null,
            'tool_schema'      => ($args['trigger_type'] === 'manual' && is_array($args['tool_schema'] ?? null)) ? json_encode($args['tool_schema'], JSON_UNESCAPED_UNICODE) : null,
        ];

        try {
            if ($existing) {
                $existing->fill($attributes)->save();
                $workflow = $existing;
            }
            else {
                $workflow = Workflow::create($attributes + [
                    'tenant_id' => $tenantId,
                    'slug'      => WorkflowPorter::uniqueSlug((string) ($args['slug'] ?? '') ?: $name, $tenantId),
                ]);
            }
        }
        catch (\Throwable $e) {
            return ['saved' => false, 'errors' => ['No se pudo guardar: ' . $e->getMessage()]];
        }

        return [
            'saved'     => true,
            'id'        => $workflow->id,
            'status'    => 'draft',
            'active'    => false,
            'editor_url' => static::tenantUrl($tenantId, \Backend::url('aero/workflows/workflows/update/' . $workflow->id)),
            'warnings'  => $warnings,
            'next_steps' => 'Una persona debe abrirlo en el editor, revisar cada nodo, probarlo con «Probar» y publicarlo. Nada se ejecuta solo hasta entonces.',
        ];
    }

    /** Enlace del backend en el host del tenant (master.…, no panel.…), si Sites está instalado. */
    protected static function tenantUrl(int $tenantId, string $url): string
    {
        try {
            return class_exists(\Aero\Sites\Models\Tenant::class) ? \Aero\Sites\Models\Tenant::localizeBackendUrls($tenantId, $url) : $url;
        }
        catch (\Throwable) {
            return $url; // sin datos de tenant, el enlace general sigue sirviendo
        }
    }

    /** Solo claves de configuración conocidas; el secreto del webhook nunca se guarda desde aquí. */
    protected static function cleanConfig(array $args): array
    {
        $config = is_array($args['trigger_config'] ?? null) ? $args['trigger_config'] : [];
        $allowed = ['message' => ['keyword', 'account_id', 'interactive_id'], 'event' => ['event'], 'manual' => [], 'webhook' => []];

        return array_intersect_key($config, array_flip($allowed[$args['trigger_type']] ?? []));
    }
}
