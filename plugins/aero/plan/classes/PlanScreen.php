<?php namespace Aero\Plan\Classes;

use Aero\Plan\Models\Plan;
use Aero\Plan\Models\Step;
use ApplicationException;
use BackendAuth;
use Flash;

/**
 * Lógica de pantalla compartida entre «Mi plan» (dueño) y «Planes de los
 * tenants» (equipo): cada controlador solo dice qué plan resuelve y con qué rol.
 * El plan del dueño sale siempre de su tenant, nunca de lo que envíe el navegador.
 */
trait PlanScreen
{
    /** 'client' (dueño) o 'team' (superadmin). */
    abstract protected function planRole(): string;

    abstract protected function resolvePlan(): ?Plan;

    protected function renderPlan(): string
    {
        $plan = $this->resolvePlan();
        $plan?->load(['steps', 'messages']);

        $view = (int) post('view', $plan?->current_step ?? 1);
        $view = max(1, min(Stages::COUNT, $view));

        return $this->makePartial('$/aero/plan/partials/_plan', [
            'plan'   => $plan,
            'role'   => $this->planRole(),
            'view'   => $view,
            'isTeam' => $this->planRole() === 'team',
        ]);
    }

    protected function stageFromPost(): Step
    {
        $plan = $this->resolvePlan() ?? throw new ApplicationException('No hay un plan.');

        return $plan->steps()->where('number', (int) post('view', 1))->firstOrFail();
    }

    protected function planUpdate(): array
    {
        return ['#plan-root' => $this->renderPlan()];
    }

    /** Respuesta ligera del autoguardado: no repinta el formulario, así no se pierde el foco. */
    protected function planAutosaveResponse(Step $step): array
    {
        $step = $step->fresh(['plan']);

        return [
            '#plan-progress' => $this->makePartial('$/aero/plan/partials/_progress', ['step' => $step]),
            '#plan-actions'  => $this->makePartial('$/aero/plan/partials/_actions', [
                'plan' => $step->plan, 'step' => $step, 'isTeam' => false, 'view' => $step->number,
                'editableClient' => $step->plan->status === 'active' && $step->isEditable(), 'editableTeam' => false,
            ]),
        ];
    }

    public function onView()
    {
        return $this->planUpdate();
    }

    public function onSend()
    {
        $step = $this->stageFromPost();
        $team = $this->planRole() === 'team';

        if ($team) {
            $this->saveTeamFields($step);
        } elseif ($step->isEditable()) {
            PlanService::saveClient($step, (array) post('a'));
        }

        PlanService::post($step->plan, $step->number, $team ? 'team' : 'client', BackendAuth::getUser(), post('message'));
        Flash::success('Mensaje enviado.');

        return $this->planUpdate();
    }

    protected function saveTeamFields(Step $step): void
    {
        if (in_array($step->status, ['open', 'review', 'changes'], true)) {
            PlanService::saveTeam($step, (array) post('t'));
        }
    }
}
