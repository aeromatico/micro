<?php namespace Aero\Finance\Controllers;

use Aero\Finance\Classes\AccountSeeder;
use Aero\Finance\Classes\CurrentTenant;
use Aero\Finance\Classes\ScopesToTenant;
use Backend\Classes\Controller;
use BackendMenu;

class Movements extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.finance.use', 'aero.finance.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Finance', 'finance', 'movements');
    }

    /** Lo que crea un tenant es suyo; el superadmin elige el negocio en el formulario. */
    public function formBeforeCreate($model): void
    {
        $model->tenant_id = CurrentTenant::id();
        if (!$model->tenant_id) {
            throw new \ApplicationException('No se pudo determinar el negocio de este libro.');
        }
    }

    /** Primer uso: siembra el plan de cuentas del tenant. */
    protected function ensureChart(): void
    {
        if ($id = CurrentTenant::id()) {
            AccountSeeder::ensure($id);
        }
    }

    protected function warnIfDisabled(): void
    {
        if (!\Aero\Finance\Models\FinanceSettings::isEnabled(CurrentTenant::id())) {
            \Flash::warning('Finanzas está desactivado: solo consulta. Actívelo en Configuración.');
        }
    }

    public function index()
    {
        $this->ensureChart();
        $this->warnIfDisabled();
        $this->asExtension('ListController')->index();
    }

    public function create()
    {
        $this->ensureChart();
        $this->asExtension('FormController')->create();
    }

    public function update_onVoid($recordId = null)
    {
        $movement = \Aero\Finance\Models\Movement::visible()->findOrFail($recordId);
        app(\Aero\Finance\Classes\MovementService::class)->void($movement, 'Anulado desde el panel');
        \Flash::success('Movimiento anulado.');

        return \Backend::redirect('aero/finance/movements');
    }
}
