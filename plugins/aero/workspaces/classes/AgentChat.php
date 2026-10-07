<?php namespace Aero\Workspaces\Classes;

use Aero\Workspaces\Jobs\RunAgentTurnJob;
use Aero\Workspaces\Models\Message;
use Aero\Workspaces\Models\Settings;
use Aero\Workspaces\Models\Staff;

/**
 * La conversación del tenant con un agente real. Enviar un mensaje es rápido
 * (guarda y encola); la respuesta llega cuando el job termina, y la pantalla la
 * consulta con `history`.
 */
class AgentChat
{
    public const MAX_MESSAGE = 4000;

    /**
     * @throws \DomainException
     */
    public static function send(int $tenantId, ?int $userId, string $slug, string $text, bool $dispatch = true): array
    {
        $staff = static::liveAgent($tenantId, $slug);
        $text = trim($text);

        if ($text === '') {
            throw new \DomainException('Escribe tu mensaje.');
        }

        if (mb_strlen($text) > static::MAX_MESSAGE) {
            throw new \DomainException('El mensaje es demasiado largo (máximo ' . static::MAX_MESSAGE . ' caracteres).');
        }

        static::expireStale($tenantId, $staff->id);

        if (Message::thread($tenantId, $staff->id)->where('status', 'pending')->exists()) {
            throw new \DomainException("{$staff->name} todavía está trabajando en tu mensaje anterior.");
        }

        $key = "workspaces.agent.{$tenantId}." . now()->format('YmdH');
        \Cache::add($key, 0, 3700);

        if (\Cache::increment($key) > Settings::agentTurnsPerHour()) {
            throw new \DomainException('Llegaste al límite de mensajes por hora con los agentes. Inténtalo más tarde.');
        }

        Message::create(['tenant_id' => $tenantId, 'staff_id' => $staff->id, 'user_id' => $userId, 'role' => 'user', 'content' => $text, 'status' => 'done']);
        $reply = Message::create(['tenant_id' => $tenantId, 'staff_id' => $staff->id, 'role' => 'assistant', 'status' => 'pending']);

        if ($dispatch) {
            RunAgentTurnJob::dispatch($reply->id);
        }

        return static::history($tenantId, $slug);
    }

    /** Mensajes recientes (más viejos primero) y si hay una respuesta en camino. */
    public static function history(int $tenantId, string $slug, int $limit = 40): array
    {
        $staff = static::liveAgent($tenantId, $slug);
        static::expireStale($tenantId, $staff->id);

        $rows = Message::thread($tenantId, $staff->id)->orderByDesc('id')->limit(max(1, min(100, $limit)))->get()->reverse()->values();

        return [
            'agent'    => ['slug' => $staff->slug, 'name' => $staff->name],
            'pending'  => $rows->contains(fn ($m) => $m->status === 'pending'),
            'messages' => $rows->map(fn (Message $m) => [
                'id'        => (int) $m->id,
                'role'      => $m->role,
                'status'    => $m->status,
                'content'   => (string) $m->content,
                'error'     => $m->error,
                'workflows' => (array) ($m->meta['workflows'] ?? []),
                'tools'     => array_values(array_unique(array_column((array) ($m->meta['tools'] ?? []), 'name'))),
            ])->all(),
        ];
    }

    /** El agente debe estar en el equipo del tenant (el orquestador siempre) y tener herramientas reales. */
    protected static function liveAgent(int $tenantId, string $slug): Staff
    {
        $staff = Staff::active()->with('skills')->where('slug', $slug)->first();

        if (!$staff || (!$staff->is_orchestrator && !in_array((int) $staff->id, Workspace::hiredIds($tenantId), true))) {
            throw new \DomainException('Ese agente no está en tu equipo. Contrátalo primero en el Mercado.');
        }

        if (!AgentRunner::isLive($staff)) {
            throw new \DomainException("{$staff->name} todavía no puede trabajar de verdad: sus encargos son una simulación.");
        }

        return $staff;
    }

    /** Un job caído no debe dejar la conversación «pendiente» para siempre. */
    protected static function expireStale(int $tenantId, int $staffId): void
    {
        Message::thread($tenantId, $staffId)->where('status', 'pending')
            ->where('created_at', '<', now()->subMinutes(AgentRunner::STALE_MINUTES))
            ->update(['status' => 'error', 'error' => 'La respuesta tardó demasiado. Vuelve a intentarlo.']);
    }
}
