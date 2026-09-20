<?php namespace Aero\Crm\Controllers;

use Aero\Crm\Classes\TenantUsers;
use Backend\Classes\Controller;
use BackendMenu;

class QuickReplies extends Controller
{
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = ['aero.crm.manage_quick_replies'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Crm', 'crm', 'crm-respuestas');
    }

    public function listExtendQuery($query): void
    {
        $query->inScope(TenantUsers::currentTenantId());
    }

    public function formExtendQuery($query): void
    {
        $query->inScope(TenantUsers::currentTenantId());
    }

    public function formExtendModel($model): void
    {
        if (!$model->exists) {
            $model->tenant_id = TenantUsers::currentTenantId();
        }
    }
}
