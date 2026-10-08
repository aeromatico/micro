<?php namespace Aero\Workspaces\Classes;

use Aero\Workspaces\Classes\Llm\ConnectorLlm;
use Aero\Workspaces\Classes\Llm\LlmDriver;
use Aero\Workspaces\Models\Message;
use Aero\Workspaces\Models\Settings;
use Aero\Workspaces\Models\Staff;

/**
 * El motor que hace trabajar de verdad a un agente: arma su prompt (el del
 * superadmin + sus skills), le da las herramientas que sus skills declaran y
 * repite «el modelo piensa → ejecuta herramientas» hasta tener una respuesta.
 *
 * Reglas duras (no dependen de lo que diga el modelo):
 *  - El tenant es el del mensaje; las herramientas lo reciben del motor, jamás
 *    de los argumentos que invente el modelo.
 *  - Un agente solo puede usar las herramientas declaradas por SUS skills.
 *  - Solo lee el cuerpo de sus propios skills (`skill_read`).
 *  - Tope de vueltas por turno y de tamaño por resultado de herramienta.
 */
class AgentRunner
{
    public const MAX_ITERATIONS = 10;
    public const HISTORY = 24;
    public const MAX_RESULT_CHARS = 14000;
    public const STALE_MINUTES = 6;

    public const READ_SKILL = 'skill_read';

    /** Herramientas internas del motor (no están en el registro público): solo para el orquestador. */
    public const DELEGATE = 'team_delegate';
    public const INTERNAL_TOOLS = [self::DELEGATE];

    /** Fábrica de modelos (solo pruebas): fn (Staff): LlmDriver. */
    public static ?\Closure $driverFactory = null;

    /** ¿Tiene este agente herramientas reales (por sus skills)? */
    public static function isLive(Staff $staff): bool
    {
        return (bool) static::toolNames($staff);
    }

    /** Nombres de herramientas declaradas por los skills del agente. */
    public static function toolNames(Staff $staff): array
    {
        $names = [];

        foreach ($staff->skills as $skill) {
            foreach ((array) $skill->tools as $name) {
                if (is_string($name)) {
                    $names[$name] = true;
                }
            }
        }

        $allowed = static::registry() + ($staff->is_orchestrator ? array_fill_keys(static::INTERNAL_TOOLS, true) : []);

        return array_keys(array_intersect_key($names, $allowed));
    }

    protected static function registry(): array
    {
        return class_exists(\Aero\Chatbots\Classes\AiToolRegistry::class) ? \Aero\Chatbots\Classes\AiToolRegistry::all() : [];
    }

    /** Herramientas del agente (+ lectura de skills) en formato del registro. */
    public static function tools(Staff $staff): array
    {
        $all = static::registry();
        $tools = array_intersect_key($all, array_flip(static::toolNames($staff)));

        if (isset($tools[static::DELEGATE]) || in_array(static::DELEGATE, static::toolNames($staff), true)) {
            $tools[static::DELEGATE] = [
                'description' => 'Encarga UN paso a un agente de tu equipo que trabaja de verdad (live=true en workspaces_team). Úsala solo con un plan que la persona ya aprobó. '
                    . 'Devuelve lo que hizo el agente (y los borradores que creó). Un agente a la vez; si devuelve una pregunta, hazla tú a la persona.',
                'parameters'  => ['type' => 'object', 'properties' => [
                    'slug'         => ['type' => 'string', 'description' => 'Identificador (slug) del agente.'],
                    'brief'        => ['type' => 'string', 'description' => 'Qué debe hacer, con todos los datos que la persona dio (textos, cuentas, preferencias).'],
                    'agreed_plan'  => ['type' => 'string', 'description' => 'El plan que la persona aprobó, en pasos simples.'],
                ], 'required' => ['slug', 'brief', 'agreed_plan']],
                'handler'     => null,
            ];
        }

        $tools[static::READ_SKILL] = [
            'description' => 'Lee el contenido completo de uno de TUS skills (instrucciones detalladas). Hazlo al empezar, antes de actuar.',
            'parameters'  => ['type' => 'object', 'properties' => ['slug' => ['type' => 'string', 'description' => 'Identificador (slug) del skill.']], 'required' => ['slug']],
            'handler'     => null,
        ];

        return $tools;
    }

    public static function systemPrompt(Staff $staff, string $tenantName = ''): string
    {
        $prompt = trim(preg_replace('/\s*\[BORRADOR[^\]]*\]/u', '', (string) $staff->system_prompt));
        $lines = [];

        foreach ($staff->skills as $skill) {
            $lines[] = "- `{$skill->slug}` — {$skill->name}: {$skill->description}";
        }

        return trim($prompt . "\n\n"
            . '# Contexto' . "\n"
            . 'Hoy es ' . now()->translatedFormat('l j \d\e F \d\e Y') . '. Hablas con una persona de «' . ($tenantName ?: 'tu cliente') . '». Responde siempre en español.' . "\n\n"
            . '# Tus skills' . "\n"
            . ($lines ? implode("\n", $lines) . "\n\nAntes de actuar, lee con `" . static::READ_SKILL . "` el skill que corresponda; ahí están tus instrucciones completas." : '(sin skills)'));
    }

    /**
     * Completa un mensaje «pendiente» del agente. Nunca lanza: deja el error
     * en el propio mensaje para que la pantalla lo muestre.
     */
    public static function run(Message $reply, ?LlmDriver $llm = null, ?string $extraSystem = null, int $maxIterations = self::MAX_ITERATIONS): void
    {
        try {
            $staff = Staff::with('skills')->findOrFail($reply->staff_id);
            $llm ??= static::driverFor($staff);
            $tenantId = (int) $reply->tenant_id;

            $messages = static::conversation($staff, $reply);

            if ($extraSystem) {
                $messages[0]['content'] .= "\n\n" . $extraSystem;
            }
            $tools = static::tools($staff);
            $trace = [];
            $workflows = [];
            $cache = [];

            for ($i = 1; $i <= $maxIterations; $i++) {
                $turn = $llm->chat($messages, $tools);

                if (!$turn['calls']) {
                    $text = trim((string) $turn['text']);

                    if ($text === '') {
                        throw new \RuntimeException('El agente no devolvió una respuesta.');
                    }

                    $reply->update(['content' => $text, 'status' => 'done', 'meta' => ['tools' => $trace, 'workflows' => $workflows, 'working' => []]]);

                    return;
                }

                $messages[] = $turn['assistant'];
                $results = [];

                foreach ($turn['calls'] as $call) {
                    $key = $call['name'] . ':' . json_encode($call['arguments']);

                    if (!array_key_exists($key, $cache)) {
                        $cache[$key] = static::execute($staff, $tools, (string) $call['name'], (array) $call['arguments'], $tenantId, $reply);
                    }

                    $result = $cache[$key];
                    $trace[] = ['name' => $call['name'], 'ok' => !isset($result['error'])];

                    if ($call['name'] === 'workflows_save_draft' && !empty($result['saved'])) {
                        $workflows[] = ['id' => $result['id'], 'name' => (string) ($call['arguments']['name'] ?? 'Workflow'), 'url' => $result['editor_url'] ?? null, 'action' => 'create'];
                    }

                    if (in_array($call['name'], ['workflows_update', 'workflows_revert'], true) && (!empty($result['updated']) || !empty($result['reverted']))) {
                        $workflows[] = ['id' => $result['id'], 'name' => (string) ($result['name'] ?? 'Workflow'), 'url' => $result['editor_url'] ?? null, 'action' => $call['name'] === 'workflows_revert' ? 'revert' : 'update'];
                    }

                    // Lo que creó el agente al que se delegó también lo ve la persona en este chat.
                    foreach ((array) ($result['workflows'] ?? []) as $made) {
                        $workflows[] = $made;
                    }

                    $results[] = ['id' => $call['id'], 'result' => static::clip($result)];
                }

                foreach ($llm->toolMessages($results) as $m) {
                    $messages[] = $m;
                }
            }

            throw new \RuntimeException('El agente dio demasiadas vueltas sin terminar. Intenta de nuevo con un encargo más concreto.');
        }
        catch (\Throwable $e) {
            \Log::warning('aero.workspaces: el agente falló', ['message_id' => $reply->id, 'error' => $e->getMessage()]);

            $reply->update(['status' => 'error', 'content' => null, 'error' => mb_substr($e->getMessage(), 0, 480), 'meta' => ['working' => []]]);
        }
    }

    /** Ejecuta una herramienta. El tenant es siempre el del mensaje. */
    protected static function execute(Staff $staff, array $tools, string $name, array $arguments, int $tenantId, ?Message $reply = null): array
    {
        if (!isset($tools[$name])) {
            return ['error' => "La herramienta «{$name}» no existe o no está entre las tuyas."];
        }

        if ($name === static::DELEGATE) {
            return Delegation::run($tenantId, $staff, $reply, $arguments);
        }

        if ($name === static::READ_SKILL) {
            $skill = $staff->skills->firstWhere('slug', (string) ($arguments['slug'] ?? ''));

            return $skill ? ['skill' => $skill->name, 'instructions' => (string) $skill->body] : ['error' => 'Ese skill no es tuyo. Los tuyos: ' . $staff->skills->pluck('slug')->implode(', ')];
        }

        try {
            return (array) call_user_func($tools[$name]['handler'], $arguments, $tenantId);
        }
        catch (\Throwable $e) {
            \Log::warning('aero.workspaces: falló una herramienta del agente', ['tool' => $name, 'error' => $e->getMessage()]);

            return ['error' => 'La herramienta falló: ' . $e->getMessage()];
        }
    }

    /** Acota el tamaño de un resultado para no desbordar el contexto del modelo. */
    protected static function clip(array $result): array
    {
        $json = json_encode($result, JSON_UNESCAPED_UNICODE);

        return strlen($json) > static::MAX_RESULT_CHARS
            ? ['truncated' => true, 'partial' => mb_substr($json, 0, static::MAX_RESULT_CHARS), 'note' => 'Resultado recortado: pide solo lo que necesites.']
            : $result;
    }

    /** system + historial reciente (sin el mensaje pendiente que se está completando). */
    protected static function conversation(Staff $staff, Message $reply): array
    {
        try {
            $tenantName = class_exists(\Aero\Sites\Models\Tenant::class) ? (string) \Aero\Sites\Models\Tenant::where('id', $reply->tenant_id)->value('name') : '';
        }
        catch (\Throwable) {
            $tenantName = ''; // el nombre solo es contexto: sin él el agente igual trabaja
        }

        $messages = [['role' => 'system', 'content' => static::systemPrompt($staff, $tenantName) . static::workingContext((int) $reply->tenant_id)]];

        $history = Message::thread((int) $reply->tenant_id, (int) $reply->staff_id)
            ->where('status', 'done')->where('id', '<', $reply->id)->orderByDesc('id')->limit(static::HISTORY)->get()->reverse();

        foreach ($history as $m) {
            $content = (string) $m->content;

            // Para que el agente recuerde lo que ya creó o cambió en turnos anteriores.
            foreach ((array) ($m->meta['workflows'] ?? []) as $w) {
                $verb = match ($w['action'] ?? 'create') { 'update' => 'Actualizaste el workflow', 'revert' => 'Deshiciste el último cambio del workflow', default => 'Guardaste el borrador' };
                $content .= "\n[{$verb} #{$w['id']} «{$w['name']}»]";
            }

            $messages[] = ['role' => $m->role === 'assistant' ? 'assistant' : 'user', 'content' => $content];
        }

        return $messages;
    }

    /**
     * «El flujo en el que estamos trabajando»: el último workflow que el equipo creó o cambió para este
     * cliente. Si la persona dice «ese flujo» o pide un cambio sin nombrarlo, es este: se EDITA, no se crea otro.
     */
    protected static function workingContext(int $tenantId): string
    {
        try {
            return static::lastTouchedWorkflow($tenantId);
        }
        catch (\Throwable) {
            return ''; // es solo contexto: sin él el agente igual trabaja
        }
    }

    protected static function lastTouchedWorkflow(int $tenantId): string
    {
        $last = null;

        foreach (Message::where('tenant_id', $tenantId)->where('role', 'assistant')->where('status', 'done')->orderByDesc('id')->limit(30)->get() as $m) {
            foreach (array_reverse((array) ($m->meta['workflows'] ?? [])) as $w) {
                $last = $w;
                break 2;
            }
        }

        if (!$last || !class_exists(\Aero\Workflows\Models\Workflow::class)) {
            return '';
        }

        $wf = \Aero\Workflows\Models\Workflow::where('tenant_id', $tenantId)->find($last['id']);

        if (!$wf) {
            return '';
        }

        return "\n\n# Flujo en el que estamos trabajando\nEl último workflow que el equipo tocó para este cliente es el #{$wf->id} «{$wf->name}» (estado: {$wf->status}, "
            . ($wf->is_active ? 'activo' : 'apagado') . "). Si la persona pide un cambio sin nombrar otro flujo («agrégale…», «cámbiale…», «ese flujo»), es ESTE: léelo con workflows_get y edítalo con workflows_update; "
            . 'solo creas uno nuevo si pide algo distinto o lo dice expresamente. Ante la duda, pregúntale cuál.';
    }

    /** El conector de IA del agente: el suyo si es de chat; si no, el de Ajustes; si no, el primero activo. */
    public static function driverFor(Staff $staff): LlmDriver
    {
        if (static::$driverFactory) {
            return (static::$driverFactory)($staff);
        }

        if (!class_exists(\Aero\Connector\Models\Connector::class) || !class_exists(\Aero\Chatbots\Classes\AiToolRegistry::class)) {
            throw new \RuntimeException('Faltan Aero.Connector y Aero.Chatbots para ejecutar agentes.');
        }

        $ai = fn ($q) => $q->whereIn('type', ['ai_openai_compatible', 'ai_anthropic'])->where('is_enabled', true);
        $connector = ($staff->connector_id ? $ai(\Aero\Connector\Models\Connector::where('id', $staff->connector_id))->first() : null)
            ?: (Settings::agentConnectorId() ? $ai(\Aero\Connector\Models\Connector::where('id', Settings::agentConnectorId()))->first() : null)
            ?: $ai(\Aero\Connector\Models\Connector::query())->orderBy('id')->first();

        if (!$connector) {
            throw new \RuntimeException('No hay un modelo de IA configurado para los agentes (Ajustes → Workspaces).');
        }

        return new ConnectorLlm($connector, Settings::agentModel());
    }
}
