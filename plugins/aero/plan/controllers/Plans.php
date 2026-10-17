<?php namespace Aero\Plan\Controllers;

use Aero\Plan\Classes\PlanScreen;
use Aero\Plan\Classes\PlanService;
use Aero\Plan\Models\Plan as PlanModel;
use ApplicationException;
use Backend\Classes\Controller;
use BackendAuth;
use BackendMenu;
use Flash;

/** «Planes de los tenants»: la bandeja y la revisión del equipo (superadmins). */
class Plans extends Controller
{
    use PlanScreen;

    public $implement = [\Backend\Behaviors\ListController::class];

    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = ['aero.plan.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Plan', 'plan', 'plans');
        $this->addCss('/plugins/aero/plan/assets/css/plan.css?v=1');
        $this->addJs('/plugins/aero/plan/assets/js/plan.js?v=1');
    }

    public function review($id = null)
    {
        $this->pageTitle = 'Revisión del plan';
        $this->vars['planId'] = (int) $id;
        $this->vars['plan'] = PlanModel::findOrFail((int) $id);
    }

    protected function planRole(): string
    {
        return 'team';
    }

    protected function resolvePlan(): ?PlanModel
    {
        $id = (int) (post('plan_id') ?: ($this->vars['planId'] ?? 0));

        return $id ? PlanModel::find($id) : null;
    }

    public function onSaveTeam()
    {
        $this->saveTeamFields($this->stageFromPost());
        Flash::success('Cambios guardados.');

        return $this->planUpdate();
    }

    public function onApprove()
    {
        $step = $this->stageFromPost();
        $this->saveTeamFields($step);
        PlanService::approve($step->fresh(), BackendAuth::getUser(), post('message'));
        Flash::success('Paso aprobado.');

        return $this->planUpdate();
    }

    public function onChanges()
    {
        $step = $this->stageFromPost();
        $this->saveTeamFields($step);
        PlanService::requestChanges($step->fresh(), BackendAuth::getUser(), post('message'));
        Flash::success('Cambios solicitados al dueño.');

        return $this->planUpdate();
    }
}
