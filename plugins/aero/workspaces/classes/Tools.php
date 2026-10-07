<?php namespace Aero\Workspaces\Classes;

/**
 * Herramientas (AI tools / MCP) del Mercado y la Oficina de agentes. Misma
 * lógica que las pantallas del panel (Workspace / Hiring / Tasks), así que
 * ofrecen exactamente lo mismo y con las mismas reglas.
 *
 * Reglas duras: el tenant lo pone el motor (segundo argumento del handler);
 * contratar y enviar encargos exigen `confirm: true` (sin él solo devuelven lo
 * que pasaría); nunca se expone el prompt de sistema ni el modelo de un agente.
 * Los encargos son una SIMULACIÓN y las respuestas lo dicen.
 */
class Tools
{
    public const CATEGORY = 'workspaces';

    public static function tools(): array
    {
        $tool = fn (string $description, array $properties, array $required, string $method) => [
            'description' => $description,
            'category'    => static::CATEGORY,
            'parameters'  => ['type' => 'object', 'properties' => $properties ?: new \stdClass(), 'required' => $required],
            'handler'     => [static::class, $method],
        ];

        $confirm = ['confirm' => ['type' => 'boolean', 'description' => 'false o ausente: solo muestra lo que pasaría. true: lo hace de verdad. Pídelo a la persona antes de enviar true.']];

        return [
            'workspaces_market' => $tool(
                'Mercado de agentes: los agentes de IA que el cliente puede contratar (rol, rareza, skills, precio de contratación y por encargo, y si ya están en su equipo). Admite filtros.',
                [
                    'category' => ['type' => 'string', 'enum' => ['video', 'imagen', 'contenido', 'ppt', 'info', 'automatizacion']],
                    'rarity'   => ['type' => 'string', 'enum' => ['r', 'sr', 'ssr']],
                    'q'        => ['type' => 'string', 'description' => 'Busca en nombre, rol, descripción y etiquetas.'],
                    'sort'     => ['type' => 'string', 'enum' => ['hires', 'cost', 'name'], 'description' => 'Más contratados, menor costo o nombre.'],
                ],
                [], 'market'
            ),
            'workspaces_agent' => $tool(
                'Perfil de un agente del mercado: biografía, capacidades, skills, guía de uso y tarifas.',
                ['slug' => ['type' => 'string', 'description' => 'Identificador del agente (campo slug de workspaces_market).']],
                ['slug'], 'agent'
            ),
            'workspaces_team' => $tool(
                'El equipo del cliente: el orquestador (siempre presente) y los agentes contratados, más sus puntos y el encargo en curso si lo hay.',
                [], [], 'team'
            ),
            'workspaces_hire' => $tool(
                'Contrata a un agente para el equipo del cliente. Sin confirm:true solo muestra el costo. El orquestador no se contrata (ya está incluido).',
                ['slug' => ['type' => 'string']] + $confirm,
                ['slug'], 'hire'
            ),
            'workspaces_skills' => $tool(
                'Skills disponibles: oficiales, del Skill Hub y las personales del cliente, con cuántos agentes las usan.',
                [], [], 'skills'
            ),
            'workspaces_submit_task' => $tool(
                'Envía un encargo al orquestador, que lo reparte entre el equipo. Sin confirm:true devuelve el plan y el costo estimado. IMPORTANTE: la ejecución es una simulación; el encargo se registra pero todavía no entrega resultados.',
                ['brief' => ['type' => 'string', 'description' => 'Qué se quiere lograr, con público y formato si aplica (máx. 2000 caracteres).']] + $confirm,
                ['brief'], 'submitTask'
            ),
            'workspaces_tasks' => $tool(
                'Encargos recientes del cliente y su estado (en curso o terminado). Con `id` devuelve uno solo, con el detalle de quién hace qué.',
                ['id' => ['type' => 'integer'], 'limit' => ['type' => 'integer', 'description' => 'Cuántos devolver (1 a 50, por defecto 10).']],
                [], 'tasks'
            ),
        ];
    }

    public static function market(array $args, int $tenantId): array
    {
        $list = Workspace::market($tenantId);
        $q = mb_strtolower(trim((string) ($args['q'] ?? '')));

        $list = array_values(array_filter($list, function ($a) use ($args, $q) {
            return (empty($args['category']) || $a['category'] === $args['category'])
                && (empty($args['rarity']) || $a['rarity'] === $args['rarity'])
                && ($q === '' || str_contains(mb_strtolower($a['name'] . ' ' . $a['role'] . ' ' . $a['bio'] . ' ' . implode(' ', $a['tags'])), $q));
        }));

        $sort = $args['sort'] ?? null;
        usort($list, match ($sort) {
            'hires' => fn ($a, $b) => $b['contracts'] <=> $a['contracts'],
            'cost'  => fn ($a, $b) => $a['task_fee'] <=> $b['task_fee'],
            default => fn ($a, $b) => strcmp($a['name'], $b['name']),
        });

        return ['agents' => array_map(static::brief(...), $list), 'points' => Workspace::points($tenantId)];
    }

    public static function agent(array $args, int $tenantId): array
    {
        foreach (Workspace::market($tenantId) as $agent) {
            if ($agent['slug'] === (string) ($args['slug'] ?? '')) {
                return ['agent' => $agent];
            }
        }

        foreach (Workspace::team($tenantId) as $agent) {
            if ($agent['slug'] === (string) ($args['slug'] ?? '')) {
                return ['agent' => $agent];
            }
        }

        return ['error' => 'No existe ese agente.'];
    }

    public static function team(array $args, int $tenantId): array
    {
        return ['team' => array_map(static::brief(...), Workspace::team($tenantId))] + Workspace::summary($tenantId);
    }

    public static function hire(array $args, int $tenantId): array
    {
        $slug = (string) ($args['slug'] ?? '');

        try {
            if (empty($args['confirm'])) {
                return ['confirmed' => false] + Hiring::preview($tenantId, $slug) + ['next' => 'Pídele confirmación a la persona y repite con confirm:true.'];
            }

            $result = Hiring::hire($tenantId, $slug);

            return ['confirmed' => true, 'hired' => $result['agent']['name'], 'charged_points' => $result['charged']];
        }
        catch (\DomainException $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public static function skills(array $args, int $tenantId): array
    {
        return ['skills' => Workspace::skills($tenantId)];
    }

    public static function submitTask(array $args, int $tenantId): array
    {
        $brief = (string) ($args['brief'] ?? '');

        try {
            if (empty($args['confirm'])) {
                return ['confirmed' => false] + Tasks::preview($tenantId, $brief) + ['next' => 'Confirma con la persona y repite con confirm:true.'];
            }

            $task = Tasks::submit($tenantId, $brief, null, 'mcp');

            return ['confirmed' => true, 'task' => Tasks::payload($task), 'note' => 'Simulación: el equipo «trabaja» pero todavía no entrega resultados.'];
        }
        catch (\DomainException $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public static function tasks(array $args, int $tenantId): array
    {
        if (!empty($args['id'])) {
            return Tasks::find($tenantId, (int) $args['id']) ?: ['error' => 'No existe ese encargo.'];
        }

        // Sin el plan detallado: para eso se pide uno con `id`.
        return ['tasks' => array_map(fn ($t) => array_diff_key($t, ['steps' => 1]), Tasks::recent($tenantId, (int) ($args['limit'] ?? 10)))];
    }

    /** Resumen sin biografía larga ni guía (para listas). */
    protected static function brief(array $a): array
    {
        return array_intersect_key($a, array_flip(['slug', 'name', 'role', 'category', 'rarity', 'orchestrator', 'hired', 'hire_fee', 'task_fee', 'contracts']))
            + ['skills' => array_column($a['skills'], 'name')];
    }
}
