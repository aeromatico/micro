<?php namespace Aero\Crm\Controllers;

use Aero\Crm\Models\Activity;
use Aero\Sites\Traits\ResolvesCurrentTenant;
use Backend\Classes\Controller;
use BackendMenu;
use Flash;

class Activities extends Controller
{
    use ResolvesCurrentTenant;

    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = ['aero.crm.manage_activities'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Crm', 'crm', 'crm-actividades');
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

    public function onUpdateStatus($recordId = null)
    {
        $activity = Activity::forTenant($this->getCurrentTenantId())
            ->findOrFail($recordId ?: post('record_id'));

        $status = post('status');

        if (!array_key_exists($status, Activity::statusOptions())) {
            Flash::error('Estado no válido.');
            return $this->listRefresh();
        }

        $activity->status = $status;
        $activity->save();

        Flash::success('Actividad actualizada a "' . $activity->status_label . '".');
        return $this->listRefresh();
    }

    public function onComplete($recordId = null)
    {
        $activity = Activity::forTenant($this->getCurrentTenantId())->findOrFail($recordId ?: post('record_id'));
        $activity->status = Activity::STATUS_COMPLETED;
        $activity->save();

        Flash::success('Actividad marcada como completada.');
        return $this->listRefresh();
    }
}
