<?php namespace Aero\School\Controllers;

use Aero\School\Classes\CurrentTenant;
use Aero\School\Classes\ScopesToTenant;
use Backend\Classes\Controller;
use BackendMenu;

class FeePlans extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.school.use', 'aero.school.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.School', 'school', 'feeplans');
    }

    /** Lo que crea un tenant es suyo; el superadmin elige el colegio en el formulario. */
    public function formBeforeCreate($model): void
    {
        if (!CurrentTenant::isAdmin()) {
            $model->tenant_id = CurrentTenant::id();
        }
    }

    /** Genera las cuotas del concepto para todos los alumnos inscritos a los que aplica (idempotente). */
    public function update_onGenerate($recordId = null)
    {
        $plan = $this->formFindModelObject($recordId);
        $n = app(\Aero\School\Classes\Billing::class)->generate($plan);
        \Flash::success($n ? "Se generaron {$n} cuotas." : 'No había cuotas nuevas: ya estaban generadas.');

        return \Redirect::refresh();
    }
}
