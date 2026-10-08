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

    /** El tenant tiene una sola configuración: se abre directo (se crea al primer uso). */
    public function index()
    {
        $id = CurrentTenant::id();
        if (!$id) {
            throw new \ApplicationException('No se pudo determinar su negocio.');
        }
        $row = \Aero\Finance\Models\FinanceSettings::forTenant($id);

        return \Backend::redirect('aero/finance/settings/update/' . $row->id);
    }

    public function create()
    {
        $this->ensureChart();
        $this->asExtension('FormController')->create();
    }

    /** Cobros del portal solo en el libro del portal; Shop/Gimnasio solo tienen sentido en negocios. */
    public function formExtendFields($form): void
    {
        $portal = CurrentTenant::isPortal();
        if (!$portal) {
            $form->removeField('post_portal');
        }
    }

    /** Avisa de las consecuencias al cambiar el interruptor general. */
    public function formAfterSave($model): void
    {
        if (!$model->wasChanged('enabled')) {
            return;
        }

        \Flash::warning($model->enabled
            ? 'Finanzas activado. Lo cobrado mientras estuvo apagado NO se registró: cárguelo a mano como ingreso.'
            : 'Finanzas apagado: no se registrará nada (manual ni automático) hasta que lo active de nuevo.');
    }
}
