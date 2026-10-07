<?php namespace Aero\Workflows\Controllers;

use Aero\Workflows\Classes\CurrentTenant;
use Aero\Workflows\Classes\ScopesToTenant;
use Aero\Workflows\Classes\WorkflowRunner;
use Aero\Workflows\Models\Workflow;
use Backend\Classes\Controller;
use BackendMenu;
use Flash;

class Workflows extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.workflows.use', 'aero.workflows.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Workflows', 'workflows', 'workflows');
    }

    public function formBeforeCreate($model): void
    {
        if (!CurrentTenant::isAdmin()) {
            $model->tenant_id = CurrentTenant::id();
        }
    }

    /** Eliminación masiva desde la lista: solo workflows del tenant del usuario (falla cerrado). */
    public function index_onDelete()
    {
        $ids = array_filter((array) post('checked'), 'is_numeric');

        if ($ids) {
            $workflows = $this->scopeToTenant(Workflow::query())->whereIn('id', $ids)->get();

            foreach ($workflows as $workflow) {
                $workflow->delete();
            }

            Flash::success($workflows->count() === 1 ? 'Workflow eliminado.' : $workflows->count() . ' workflows eliminados.');
        }
        else {
            Flash::error('Selecciona al menos un workflow.');
        }

        return $this->listRefresh();
    }

    /** Ejecución de prueba (síncrona) con un payload JSON opcional. */
    public function update_onTestRun($recordId = null)
    {
        $workflow = $this->scopeToTenant(Workflow::query())->findOrFail($recordId);
        $payload = json_decode((string) post('test_payload', '{}'), true);

        $run = WorkflowRunner::start($workflow, is_array($payload) ? $payload : [], 'manual', sync: true);

        if (!$run) {
            Flash::error('No se pudo ejecutar: límite de ejecuciones alcanzado.');

            return;
        }

        $run->status === 'ok'
            ? Flash::success("Ejecución #{$run->id} correcta ({$run->steps_count} pasos).")
            : Flash::error("Ejecución #{$run->id} con error: {$run->error}");

        return ['#testRunLink' => '<a href="' . \Backend::url('aero/workflows/runs/preview/' . $run->id) . '">Ver ejecución #' . $run->id . '</a>'];
    }
}
