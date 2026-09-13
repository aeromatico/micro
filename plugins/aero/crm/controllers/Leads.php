<?php namespace Aero\Crm\Controllers;

use Aero\Crm\Models\Deal;
use Aero\Crm\Models\Lead;
use Aero\Crm\Models\Pipeline;
use Aero\Sites\Traits\ResolvesCurrentTenant;
use Backend\Classes\Controller;
use BackendMenu;
use Flash;
use Redirect;

class Leads extends Controller
{
    use ResolvesCurrentTenant;

    /**
     * Config del formulario de Deal, reutilizada por la acción create para
     * que "Nuevo lead" guarde un Deal directo en el pipeline sin cambiar de
     * URL (sigue siendo /backend/aero/crm/leads/create).
     */
    const DEAL_FORM_CONFIG = '$/aero/crm/controllers/deals/config_form.yaml';

    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = ['aero.crm.manage_leads'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Crm', 'crm', 'crm-leads');
    }

    /**
     * Solo para la acción create (tanto el render como el guardado AJAX)
     * cambiamos el config del formulario por el de Deal: mismos campos,
     * mismas validaciones y mismo comportamiento, pero bajo la URL de leads.
     */
    public function beforeDisplay()
    {
        if ($this->action === 'create') {
            $this->asExtension('FormController')->setConfig(
                self::DEAL_FORM_CONFIG,
                ['modelClass', 'form']
            );
        }
    }

    public function listExtendQuery($query): void
    {
        $this->scopeQueryToTenant($query);
    }

    public function formExtendModel($model): void
    {
        if ($model->exists) {
            return;
        }

        $model->tenant_id = $this->getCurrentTenantId();

        if ($model instanceof Deal) {
            $pipeline = Pipeline::seedDefaultForTenant($model->tenant_id);
            $model->pipeline_id = $pipeline->id;
            $model->stage_id = $pipeline->stages()->orderBy('sort_order')->value('id');
        }
    }

    public function onConvert($recordId = null)
    {
        $lead = Lead::forTenant($this->getCurrentTenantId())->findOrFail($recordId ?: post('record_id'));
        $result = $lead->convert();

        Flash::success('Lead convertido en contacto y deal.');

        return Redirect::to(\Backend::url('aero/crm/deals/board') . '?deal=' . $result['deal']->id);
    }
}
