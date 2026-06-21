<?php namespace Aero\Sites\Controllers;

use Aero\Sites\Traits\ResolvesCurrentTenant;
use BackendMenu;
use Backend\Classes\Controller;

class Pages extends Controller
{
    use ResolvesCurrentTenant;

    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
        \Backend\Behaviors\ReorderController::class,
    ];

    public $formConfig    = 'config_form.yaml';
    public $listConfig    = 'config_list.yaml';
    public $reorderConfig = 'config_reorder.yaml';

    public $requiredPermissions = ['aero.sites.manage_pages'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Sites', 'sites', 'pages');
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

    public function formBeforeSave($model): void
    {
        // content HTML is emitted by PuckEditor widget as a hidden textarea
        // outside of getSaveData(), so we capture it directly from POST.
        $data = post('Page', []);
        if (array_key_exists('content', $data)) {
            $model->content = $data['content'];
        }
    }
}
