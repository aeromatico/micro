<?php namespace Aero\Finance\Controllers;

use Aero\Finance\Classes\AccountSeeder;
use Aero\Finance\Classes\CurrentTenant;
use Aero\Finance\Classes\ScopesToTenant;
use Backend\Classes\Controller;
use BackendMenu;

class Settings extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.finance.use', 'aero.finance.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Finance', 'finance', 'settings');
    }

    /** Lo que crea un tenant es suyo; el superadmin elige el negocio en el formulario. */
    public function formBeforeCreate($model): void
    {
        if (!CurrentTenant::isAdmin()) {
            $model->tenant_id = CurrentTenant::id();
        }
    }

    /** Primer uso: siembra el plan de cuentas del tenant. */
    protected function ensureChart(): void
    {
        if (!CurrentTenant::isAdmin() && ($id = CurrentTenant::id())) {
            AccountSeeder::ensure($id);
        }
    }

    /** El tenant tiene una sola configuración: se abre directo (se crea al primer uso). */
    public function index()
    {
        if (!CurrentTenant::isAdmin()) {
            $id = CurrentTenant::id();
            if (!$id) {
                throw new \ApplicationException('No se pudo determinar su negocio.');
            }
            $row = \Aero\Finance\Models\FinanceSettings::forTenant($id);

            return \Backend::redirect('aero/finance/settings/update/' . $row->id);
        }

        $this->asExtension('ListController')->index();
    }

    public function create()
    {
        $this->ensureChart();
        $this->asExtension('FormController')->create();
    }
}
