<?php namespace Aero\Crm\Controllers;

use Aero\Crm\Models\Deal;
use Aero\Crm\Models\Pipeline;
use Aero\Sites\Traits\ResolvesCurrentTenant;
use Backend\Classes\Controller;
use BackendMenu;

class Deals extends Controller
{
    use ResolvesCurrentTenant;

    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = ['aero.crm.manage_deals'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Crm', 'crm', 'crm-pipeline');
    }

    public function listExtendQuery($query): void
    {
        $this->scopeQueryToTenant($query);
    }

    public function formExtendModel($model): void
    {
        if (!$model->exists) {
            $model->tenant_id = $this->getCurrentTenantId();
            $pipeline = Pipeline::seedDefaultForTenant($this->getCurrentTenantId());
            $model->pipeline_id = $pipeline->id;
            $model->stage_id = $pipeline->stages()->orderBy('sort_order')->value('id');
        }
    }

    public function board()
    {
        $this->pageTitle = 'Pipeline';
        $tenantId = $this->getCurrentTenantId();

        if (!$tenantId) {
            $this->vars['noTenant'] = true;
            return;
        }

        $pipeline = Pipeline::seedDefaultForTenant($tenantId);

        $this->vars['pipeline'] = $pipeline->load(['stages' => function ($q) {
            $q->orderBy('sort_order');
        }, 'stages.deals' => function ($q) {
            $q->orderBy('sort_order')->with(['contact', 'company']);
        }]);
    }

    /**
     * Mueve un deal a otra etapa (y/o reordena dentro de la misma columna).
     * `stage_id`/`ids` (array ordenado de deal ids en la columna destino).
     */
    public function onMoveDeal()
    {
        $tenantId = $this->getCurrentTenantId();
        $dealId   = (int) post('deal_id');
        $stageId  = (int) post('stage_id');
        $ids      = (array) post('ids', []);

        $deal = Deal::forTenant($tenantId)->findOrFail($dealId);
        $stage = \Aero\Crm\Models\PipelineStage::forTenant($tenantId)->findOrFail($stageId);

        $deal->stage_id = $stage->id;
        $deal->status = $stage->is_won ? 'won' : ($stage->is_lost ? 'lost' : 'open');
        $deal->save();

        foreach ($ids as $order => $id) {
            Deal::forTenant($tenantId)->where('id', (int) $id)->update(['sort_order' => $order]);
        }

        return ['result' => 'ok'];
    }
}
