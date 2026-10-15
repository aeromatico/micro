<?php namespace Aero\School\Controllers;

use Aero\School\Classes\CurrentTenant;
use Aero\School\Classes\ScopesToTenant;
use Backend\Classes\Controller;
use BackendMenu;

class Charges extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.school.use', 'aero.school.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.School', 'school', 'charges');
    }

    /** Lo que crea un tenant es suyo; el superadmin elige el colegio en el formulario. */
    public function formBeforeCreate($model): void
    {
        if (!CurrentTenant::isAdmin()) {
            $model->tenant_id = CurrentTenant::id();
        }
    }

    public function update_onVoid($recordId = null)
    {
        $charge = $this->formFindModelObject($recordId);

        try {
            app(\Aero\School\Classes\Billing::class)->voidCharge($charge);
        } catch (\Aero\School\Classes\SchoolException $e) {
            throw new \ApplicationException($e->getMessage());
        }

        \Flash::success('Cuota anulada.');

        return \Backend::redirect('aero/school/charges');
    }

    public function listExtendQuery($query): void
    {
        $this->scopeToTenant($query);
        $query->with('student');
    }
}
