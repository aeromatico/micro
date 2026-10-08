<?php namespace Aero\Workspaces\Classes;

use Aero\Workspaces\Models\Message;
use Aero\Workspaces\Models\Staff;

/**
 * El orquestador le encarga un paso a un agente de su equipo que trabaja de
 * verdad. El agente corre su propio turno con la misma conversación de siempre
 * (la persona la ve en su chat) y le devuelve el resultado al orquestador.
 *
 * Reglas duras: solo agentes contratados y «reales», nunca otro orquestador (así
 * nadie delega en cadena), uno a la vez por agente, y el encargo debe traer el
 * plan que la persona aprobó.
 */
class Delegation
{
    public const MAX_ITERATIONS = 8;

    protected const DELEGATED = <<<'TXT'
# Encargo delegado
Esta vez no hablas con la persona: te llega un encargo de la orquestadora con el plan YA aprobado por la persona.
- Si el plan y los datos alcanzan, hazlo ahora completo (lee tu skill, valida y guarda), sin pedir otra confirmación.
- Si falta algo esencial, no inventes: responde SOLO con la pregunta concreta que la orquestadora debe hacerle a la persona.
- Si el encargo dice que se EDITE un workflow que ya existe (con su #id), léelo con workflows_get y aplica el cambio con workflows_update (envía el grafo completo ya modificado). NO crees otro workflow para eso.
- Termina con un resumen breve y fiel de lo que hiciste: qué creaste (nombre y enlace exacto que devolvieron tus herramientas) y qué debe revisar la persona. Nunca digas que hiciste algo que no hiciste.
TXT;

    /**
     * @param array $args slug, brief, agreed_plan (los manda el modelo: se validan)
     */
    public static function run(int $tenantId, Staff $orchestrator, ?Message $parent, array $args): array
    {
        $slug = trim((string) ($args['slug'] ?? ''));
        $brief = trim((string) ($args['brief'] ?? ''));
        $plan = trim((string) ($args['agreed_plan'] ?? ''));

        if ($brief === '' || mb_strlen($plan) < 20) {
            return ['error' => 'Falta el encargo (brief) o el plan acordado con la persona (agreed_plan). Acuérdalo con ella antes de delegar.'];
        }

        $target = Staff::active()->with('skills')->where('slug', $slug)->first();

        if (!$target || $target->is_orchestrator) {
            return ['error' => 'Ese agente no existe o no se puede delegar en él. Mira workspaces_team.'];
        }

        if (!in_array((int) $target->id, Workspace::hiredIds($tenantId), true)) {
            return ['error' => "{$target->name} no está en el equipo de este cliente: contratarlo es decisión de la persona (Mercado)."];
        }

        if (!AgentRunner::isLive($target)) {
            return ['error' => "{$target->name} todavía no trabaja de verdad (sus encargos son una simulación). No le delegues: cuéntale a la persona qué parte no se puede hacer aún."];
        }

        if (Message::thread($tenantId, $target->id)->whereIn('status', ['pending', 'running'])->exists()) {
            return ['error' => "{$target->name} todavía está ocupado con otro mensaje. Espera a que termine."];
        }

        try {
            Billing::assertCanAffordTurn($tenantId, $target);
        }
        catch (\DomainException $e) {
            return ['error' => $e->getMessage() . ' Avísale a la persona.'];
        }

        static::working($parent, [$target->slug]);

        $text = "📋 Encargo de {$orchestrator->name} (orquestadora):\n{$brief}\n\nPlan acordado con la persona:\n{$plan}";
        Message::create(['tenant_id' => $tenantId, 'staff_id' => $target->id, 'role' => 'user', 'content' => $text, 'status' => 'done', 'meta' => ['from' => 'orchestrator']]);
        $reply = Message::create(['tenant_id' => $tenantId, 'staff_id' => $target->id, 'role' => 'assistant', 'status' => 'running']);

        AgentRunner::run($reply, null, static::DELEGATED, static::MAX_ITERATIONS);
        $reply->refresh();

        static::working($parent, []);

        if ($reply->status !== 'done') {
            return ['agent' => $target->name, 'status' => 'error', 'error' => (string) $reply->error ?: 'No pudo completar el encargo.'];
        }

        return [
            'agent'     => $target->name,
            'status'    => 'done',
            'result'    => (string) $reply->content,
            'workflows' => (array) ($reply->meta['workflows'] ?? []),
            'note'      => 'Resume esto fielmente a la persona y entrégale los enlaces tal cual.',
        ];
    }

    /** Quién está trabajando por encargo de este turno (la oficina lo anima). */
    protected static function working(?Message $parent, array $slugs): void
    {
        if ($parent) {
            $parent->update(['meta' => ['working' => $slugs]]);
        }
    }
}
