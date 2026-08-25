<?php namespace Aero\Crm\Controllers;

use Aero\Crm\Models\Lead;
use Aero\Sites\Traits\ResolvesCurrentTenant;
use Backend\Classes\Controller;
use BackendMenu;
use Flash;
use Redirect;

class Leads extends Controller
{
    use ResolvesCurrentTenant;

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

    public function listExtendQuery($query): void
    {
        $this->scopeQueryToTenant($query);
    }

    public function formExtendModel($model): void
    {
        if (!$model->exists) {
            $model->tenant_id = $this->getCurrentTenantId();
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
