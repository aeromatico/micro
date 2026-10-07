<?php namespace Aero\Workflows\Classes;

use Cache;
use Illuminate\Support\Str;
use Aero\Workflows\Models\Workflow;

/**
 * Escucha `aero.*` y dispara los workflows activos cuyo disparador coincide.
 * Falla cerrado: solo corre si el evento identifica un tenant y es el mismo
 * del workflow (un evento sin tenant no dispara nada).
 */
class Triggers
{
    protected const CACHE_KEY = 'aero.workflows.triggers';
    protected const MESSAGE_EVENT = 'aero.hello.messageReceived';

    public static function flush(): void
    {
        Cache::forget(static::CACHE_KEY);
    }

    /** @return array<int, array{id:int, tenant_id:?int, type:string, config:array}> */
    protected static function candidates(): array
    {
        return Cache::remember(static::CACHE_KEY, 60, function () {
            return Workflow::where('is_active', true)
                ->where('status', 'published')
                ->whereIn('trigger_type', ['event', 'message'])
                ->get()
                ->map(fn ($w) => [
                    'id' => $w->id, 'tenant_id' => $w->tenant_id,
                    'type' => $w->trigger_type, 'config' => $w->jsonField('trigger_config'),
                ])->all();
        });
    }

    public static function handle(string $eventName, array $args): void
    {
        // Anti-recursión: los eventos del propio plugin nunca disparan workflows.
        if (str_starts_with($eventName, 'aero.workflows.')) {
            return;
        }

        $tenantId = null;
        $matches = [];

        foreach (static::candidates() as $c) {
            $expected = $c['type'] === 'message' ? static::MESSAGE_EVENT : (string) ($c['config']['event'] ?? '');

            if ($expected !== '' && Str::is($expected, $eventName)) {
                $matches[] = $c;
            }
        }

        if (!$matches) {
            return;
        }

        $tenantId = static::tenantFrom($args);

        if (!$tenantId) {
            return;
        }

        foreach ($matches as $c) {
            if ((int) $c['tenant_id'] !== $tenantId) {
                continue;
            }

            if ($c['type'] === 'message' && !static::messagePasses($c['config'], $args[0] ?? null)) {
                continue;
            }

            $workflow = Workflow::find($c['id']);

            if ($workflow && $workflow->is_active) {
                WorkflowRunner::start($workflow, ['event' => $eventName, 'data' => static::normalize($args)], $c['type']);
            }
        }
    }

    protected static function tenantFrom(array $args): ?int
    {
        foreach ($args as $arg) {
            $id = null;

            if (is_array($arg)) {
                $id = $arg['tenant_id'] ?? null;
            }
            elseif (is_object($arg)) {
                $id = $arg->tenant_id ?? null;

                if (!$id && isset($arg->account_id) && class_exists(\Aero\Hello\Models\Account::class)) {
                    $id = \Aero\Hello\Models\Account::where('id', $arg->account_id)->value('tenant_id');
                }
            }

            if ($id) {
                return (int) $id;
            }
        }

        return null;
    }

    protected static function messagePasses(array $config, mixed $message): bool
    {
        if (!is_object($message) || ($message->direction ?? null) !== 'inbound') {
            return false;
        }

        if (!empty($config['account_id']) && (int) $config['account_id'] !== (int) $message->account_id) {
            return false;
        }

        // Solo si tocó una opción concreta de un menú/botón (provider_payload.interactive_id).
        $wanted = array_values(array_filter(array_map('trim', (array) ($config['interactive_id'] ?? [])), fn ($v) => $v !== ''));

        if ($wanted) {
            $payload = $message->provider_payload ?? [];
            $given = is_array($payload) ? (string) ($payload['interactive_id'] ?? '') : '';

            if ($given === '' || !in_array($given, $wanted, true)) {
                return false;
            }
        }

        $keyword = trim((string) ($config['keyword'] ?? ''));

        return $keyword === '' || stripos((string) $message->body, $keyword) !== false;
    }

    /** Solo datos serializables y acotados: nunca objetos completos. */
    protected static function normalize(array $args): array
    {
        $out = [];

        foreach ($args as $i => $arg) {
            $out[$i] = match (true) {
                is_array($arg)                           => $arg,
                is_object($arg) && method_exists($arg, 'toArray') => $arg->toArray(),
                is_scalar($arg) || $arg === null         => $arg,
                default                                  => null,
            };
        }

        return json_decode(mb_substr(json_encode($out, JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '[]', 0, 50000), true) ?: [];
    }
}
