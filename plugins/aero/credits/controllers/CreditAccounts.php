<?php namespace Aero\Credits\Controllers;

use Aero\Credits\Classes\Credits;
use Aero\Credits\Models\CreditType;
use Backend\Classes\Controller;
use BackendAuth;
use BackendMenu;
use Flash;

class CreditAccounts extends Controller
{
    public $implement = [
        \Backend\Behaviors\ListController::class,
    ];

    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = ['aero.credits.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Credits', 'credits', 'creditaccounts');
    }

    public function onLoadRechargeForm()
    {
        $this->vars['tenants']    = $this->tenantOptions();
        $this->vars['creditTypes'] = CreditType::active()->pluck('label', 'code')->all();

        return $this->makePartial('recharge_form');
    }

    public function onRecharge()
    {
        $tenantId = (int) post('tenant_id');
        $typeCode = (string) post('credit_type_code');
        $amount   = (int) post('amount');
        $reason   = post('reason') ?: 'Recarga manual';

        if (!$tenantId || !$typeCode || !$amount) {
            Flash::error('Completa tenant, color y cantidad.');
            return;
        }

        Credits::topUp($tenantId, $typeCode, $amount, $reason, BackendAuth::getUser()->id);

        Flash::success('Saldo actualizado.');

        return $this->listRefresh();
    }

    protected function tenantOptions(): array
    {
        if (class_exists(\Aero\Sites\Models\Tenant::class)) {
            return \Aero\Sites\Models\Tenant::orderBy('name')->pluck('name', 'id')->all();
        }

        return [];
    }
}
