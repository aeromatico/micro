<?php namespace Aero\Livechat\Controllers;

use Aero\Livechat\Classes\TenantScope;
use Backend\Classes\Controller;
use BackendMenu;

class Inboxes extends Controller
{
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = ['aero.livechat.manage_inboxes'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Livechat', 'livechat', 'livechat-inboxes');
    }

    public function listExtendQuery($query): void
    {
        $query->inScope(TenantScope::currentTenantId())->withCount('conversations');
    }

    public function formExtendQuery($query): void
    {
        $query->inScope(TenantScope::currentTenantId());
    }

    public function formExtendModel($model): void
    {
        if (!$model->exists) {
            $model->tenant_id = TenantScope::currentTenantId();
        }
    }
}
