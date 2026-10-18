<?php namespace Aero\Plan\Controllers;

use Aero\Plan\Classes\CurrentTenant;
use Aero\Plan\Classes\PlanScreen;
use Aero\Plan\Classes\PlanService;
use Aero\Plan\Models\Plan as PlanModel;
use ApplicationException;
use Backend;
use Backend\Classes\Controller;
use BackendMenu;
use Flash;

/** «Mi plan»: lo que ve y hace el dueño del tenant. */
class Plan extends Controller
{
    use PlanScreen;

    public $requiredPermissions = ['aero.plan.use', 'aero.plan.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Plan', 'plan', 'mi-plan');
        $this->addCss('/plugins/aero/plan/assets/css/plan.css?v=1');
        $this->addJs('/plugins/aero/plan/assets/js/plan.js?v=1');
    }

    public function index()
    {
        // Un superadmin sin tenant propio no tiene «Mi plan»: va a la bandeja.
        if (!CurrentTenant::id() && CurrentTenant::isAdmin()) {
            return Backend::redirect('aero/plan/plans');
        }

        $this->pageTitle = 'Plan de 7 días';
    }

    protected function planRole(): string
    {
        return 'client';
    }

    protected function resolvePlan(): ?PlanModel
    {
        return PlanService::forTenant(CurrentTenant::id());
    }

    protected function requireTenant(): int
    {
        return CurrentTenant::id() ?? throw new ApplicationException('Tu usuario no está asociado a un negocio.');
    }

    public function onStart()
    {
        PlanService::start($this->requireTenant());
        Flash::success('¡Empezamos! Completa el primer paso cuando quieras.');

        return $this->planUpdate();
    }

    public function onSave()
    {
        PlanService::saveClient($this->stageFromPost(), (array) post('a'));
        Flash::success('Avance guardado. Puedes volver cuando quieras.');

        return $this->planUpdate();
    }

    /** Cambió el rubro: se guarda y se repinta porque aparecen sus preguntas clave. */
    public function onRubro()
    {
        PlanService::saveClient($this->stageFromPost(), (array) post('a'));

        return $this->planUpdate();
    }

    public function onAutosave()
    {
        $step = PlanService::saveClient($this->stageFromPost(), (array) post('a'));

        return $this->planAutosaveResponse($step);
    }

    public function onSubmit()
    {
        $step = $this->stageFromPost();
        PlanService::saveClient($step, (array) post('a'));
        PlanService::submit($step->fresh());
        Flash::success('Enviado a revisión. Te avisaremos apenas haya respuesta.');

        return $this->planUpdate();
    }

    public function onPause()
    {
        PlanService::pause($this->resolvePlan() ?? throw new ApplicationException('No hay un plan.'), post('reason'));
        Flash::success('Plan en pausa. Tu avance queda guardado.');

        return $this->planUpdate();
    }

    public function onResume()
    {
        PlanService::resume($this->resolvePlan() ?? throw new ApplicationException('No hay un plan.'));
        Flash::success('Plan retomado.');

        return $this->planUpdate();
    }
}
