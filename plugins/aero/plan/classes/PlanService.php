<?php namespace Aero\Plan\Classes;

use Aero\Plan\Models\Message;
use Aero\Plan\Models\Plan;
use Aero\Plan\Models\Step;
use ApplicationException;
use Event;

/**
 * Reglas del plan: quién puede hacer qué en cada estado. Todo pasa por aquí
 * (pantallas, tests, futura API) para que la máquina de estados viva en un
 * solo lugar. Cada cambio relevante dispara un evento aero.plan.* que
 * Aero.Notify convierte en avisos por WhatsApp y correo.
 */
class PlanService
{
    public static function forTenant(?int $tenantId): ?Plan
    {
        return $tenantId ? Plan::where('tenant_id', $tenantId)->first() : null;
    }

    /** El dueño decide empezar. Un solo plan por tenant. */
    public static function start(int $tenantId): Plan
    {
        if ($existing = self::forTenant($tenantId)) {
            return $existing;
        }

        $plan = Plan::create(['tenant_id' => $tenantId, 'status' => 'active', 'started_at' => now()]);

        foreach (range(1, Stages::COUNT) as $n) {
            Step::create([
                'plan_id' => $plan->id, 'tenant_id' => $tenantId, 'number' => $n,
                'status' => $n === 1 ? 'open' : 'locked', 'answers' => [],
            ]);
        }

        self::say($plan, 1, 'team', 'Hola, cuéntanos de tu negocio en este primer paso. Puedes guardar y volver cuando quieras.');
        Event::fire('aero.plan.started', [$plan]);

        return $plan->fresh(['steps', 'messages']);
    }

    public static function guardEditable(Step $step): void
    {
        if ($step->plan->status !== 'active') {
            throw new ApplicationException('El plan está en pausa. Retómalo para seguir.');
        }
        if (!$step->isEditable()) {
            throw new ApplicationException('Este paso no se puede editar ahora.');
        }
    }

    public static function saveClient(Step $step, array $input): Step
    {
        self::guardEditable($step);

        $step->answers = Stages::sanitizeClient($step->number, $input, $step->answers ?? []);
        $step->save();

        return $step;
    }

    public static function saveTeam(Step $step, array $input): Step
    {
        if ($step->plan->status !== 'active' || $step->status === 'locked' || $step->status === 'approved') {
            throw new ApplicationException('Este paso ya no admite cambios del equipo.');
        }

        $step->answers = Stages::sanitizeTeam($step->number, $input, $step->answers ?? []);
        $step->save();

        return $step;
    }

    public static function submit(Step $step): Step
    {
        self::guardEditable($step);

        $missing = Stages::missing($step->number, $step->answers ?? []);
        if ($missing) {
            throw new ApplicationException('Faltan respuestas obligatorias: ' . implode('; ', array_slice($missing, 0, 4)) . (count($missing) > 4 ? '…' : ''));
        }

        $step->update(['status' => 'review', 'submitted_at' => now()]);
        self::say($step->plan, $step->number, 'system', 'El dueño envió este paso a revisión');
        Event::fire('aero.plan.stageSubmitted', [$step->plan, $step]);

        return $step;
    }

    public static function approve(Step $step, $user, ?string $note = null): Step
    {
        if ($step->plan->status !== 'active' || $step->status !== 'review') {
            throw new ApplicationException('Solo se puede aprobar un paso que está en revisión.');
        }

        $plan = $step->plan;
        $step->update(['status' => 'approved', 'approved_at' => now(), 'approved_by' => $user?->id]);

        if ($note = self::clean($note)) {
            self::say($plan, $step->number, 'team', $note, $user?->id);
        }
        self::say($plan, $step->number, 'system', 'Clouds aprobó este paso');

        $next = $plan->steps()->where('number', $step->number + 1)->first();
        if ($next) {
            $next->update(['status' => 'open']);
            self::say($plan, $next->number, 'team', 'Paso ' . $step->number . ' aprobado. Ya puedes completar «' . Stages::find($next->number)['name'] . '».', $user?->id);
            Event::fire('aero.plan.stageApproved', [$plan, $step, $next]);
        } else {
            $plan->update(['status' => 'completed', 'completed_at' => now()]);
            Event::fire('aero.plan.stageApproved', [$plan, $step, null]);
            Event::fire('aero.plan.completed', [$plan]);
        }

        return $step;
    }

    public static function requestChanges(Step $step, $user, ?string $note): Step
    {
        if ($step->plan->status !== 'active' || $step->status !== 'review') {
            throw new ApplicationException('Solo se pueden pedir cambios en un paso que está en revisión.');
        }
        if (!($note = self::clean($note))) {
            throw new ApplicationException('Escribe qué debe ajustar el dueño.');
        }

        $step->update(['status' => 'changes']);
        self::say($step->plan, $step->number, 'team', $note, $user?->id, 'changes');
        self::say($step->plan, $step->number, 'system', 'Clouds pidió cambios');
        Event::fire('aero.plan.changesRequested', [$step->plan, $step, $note]);

        return $step;
    }

    public static function post(Plan $plan, int $stepNumber, string $author, $user, ?string $body): ?Message
    {
        if (!($body = self::clean($body))) {
            return null;
        }
        if ($plan->status === 'completed' || !in_array($author, ['client', 'team'], true)) {
            throw new ApplicationException('No se puede escribir en este plan.');
        }
        $step = $plan->steps()->where('number', $stepNumber)->first();
        if (!$step || $step->status === 'locked') {
            throw new ApplicationException('Ese paso todavía no está abierto.');
        }

        $message = self::say($plan, $stepNumber, $author, $body, $user?->id);
        Event::fire('aero.plan.messagePosted', [$plan, $message]);

        return $message;
    }

    /** Dejar el plan es una pausa: no borra nada y detiene el contador. */
    public static function pause(Plan $plan, ?string $reason = null): Plan
    {
        if ($plan->status !== 'active') {
            return $plan;
        }

        $plan->update(['status' => 'paused', 'paused_at' => now(), 'leave_reason' => mb_substr((string) $reason, 0, 120) ?: null]);
        Event::fire('aero.plan.paused', [$plan]);

        return $plan;
    }

    public static function resume(Plan $plan): Plan
    {
        if ($plan->status !== 'paused') {
            return $plan;
        }

        $plan->update([
            'status' => 'active',
            'paused_seconds' => (int) $plan->paused_seconds + max(0, now()->getTimestamp() - ($plan->paused_at?->getTimestamp() ?? now()->getTimestamp())),
            'paused_at' => null,
            'leave_reason' => null,
        ]);
        Event::fire('aero.plan.resumed', [$plan]);

        return $plan;
    }

    protected static function say(Plan $plan, int $stepNumber, string $author, string $body, ?int $userId = null, ?string $tag = null): Message
    {
        return Message::create([
            'plan_id' => $plan->id, 'tenant_id' => $plan->tenant_id, 'step_number' => $stepNumber,
            'author' => $author, 'user_id' => $userId, 'body' => $body, 'tag' => $tag,
        ]);
    }

    protected static function clean(?string $text): ?string
    {
        $text = trim((string) $text);

        return $text === '' ? null : mb_substr($text, 0, 2000);
    }
}
