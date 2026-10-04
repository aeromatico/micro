<?php namespace Aero\Pos\Controllers;

use Aero\Pos\Classes\PosProvisioner;
use Aero\Sites\Traits\ResolvesCurrentTenant;
use Backend\Classes\Controller;
use BackendMenu;

class PaymentMethods extends Controller
{
    use ResolvesCurrentTenant;

    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = ['aero.pos.manage'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Pos', 'pos', 'pos-metodos');
    }

    public function index()
    {
        if ($tenantId = $this->getCurrentTenantId()) {
            PosProvisioner::ensureDefaults($tenantId);
        }
        $this->asExtension('ListController')->index();
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
}
