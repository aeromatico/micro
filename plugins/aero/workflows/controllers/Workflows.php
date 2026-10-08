<?php namespace Aero\Workflows\Controllers;

use Aero\Workflows\Classes\CurrentTenant;
use Aero\Workflows\Classes\ScopesToTenant;
use Aero\Workflows\Classes\WorkflowPorter;
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

    /**
     * Descarga un workflow (`/export/ID`) o varios (`/export?ids=1,2`) como JSON,
     * sin ids del tenant ni secretos (ver WorkflowPorter). Solo los del tenant del usuario.
     */
    public function export($recordId = null)
    {
        $ids = $recordId ? [(int) $recordId] : array_filter(array_map('intval', explode(',', (string) request('ids'))));

        $workflows = $ids ? $this->scopeToTenant(Workflow::query())->whereIn('id', $ids)->orderBy('id')->get() : collect();

        if ($workflows->isEmpty()) {
            Flash::error('No hay workflows para exportar.');

            return \Backend::redirect('aero/workflows/workflows');
        }

        $exports = $workflows->map(fn ($w) => WorkflowPorter::export($w))->all();
        $single = count($exports) === 1;
        $payload = $single ? $exports[0] : WorkflowPorter::bundle($exports);
        $file = $single ? $workflows->first()->slug . '.workflow.json' : 'workflows-' . now()->format('Ymd-His') . '.json';

        return \Response::make(
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
            200,
            ['Content-Type' => 'application/json; charset=utf-8', 'Content-Disposition' => 'attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $file) . '"']
        );
    }

    public function import(): void
    {
        $this->pageTitle = 'Importar workflows';
    }

    /** Importa uno o varios workflows: siempre borradores desactivados, del tenant del usuario. */
    public function onImport()
    {
        $tenantId = CurrentTenant::isAdmin() ? null : CurrentTenant::id();

        if (!CurrentTenant::isAdmin() && !$tenantId) {
            throw new \ApplicationException('No se pudo determinar tu tenant.');
        }

        $upload = request()->file('file');
        $json = $upload ? (string) @file_get_contents($upload->getRealPath()) : (string) post('json');

        try {
            $items = WorkflowPorter::parse($json);
        }
        catch (\InvalidArgumentException $e) {
            throw new \ApplicationException($e->getMessage());
        }

        $done = [];
        $failed = [];

        foreach ($items as $item) {
            try {
                $done[] = WorkflowPorter::import((array) $item, $tenantId);
            }
            catch (\InvalidArgumentException $e) {
                $failed[] = $e->getMessage();
            }
            catch (\ValidationException|\ApplicationException $e) {
                $failed[] = 'No se pudo guardar: ' . $e->getMessage();
            }
        }

        if (!$done) {
            throw new \ApplicationException(implode("\n", array_slice($failed, 0, 5)));
        }

        Flash::success(count($done) === 1 ? 'Workflow importado como borrador.' : count($done) . ' workflows importados como borradores.');

        return ['#import-result' => $this->makePartial('import_result', ['done' => $done, 'failed' => $failed])];
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
