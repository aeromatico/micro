<?php namespace Aero\Office\Controllers;

use Aero\Office\Classes\CurrentTenant;
use Aero\Office\Classes\ScopesToTenant;
use Backend\Classes\Controller;
use BackendMenu;

class Customers extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.office.use', 'aero.office.reception', 'aero.office.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Office', 'office', 'customers');
    }

    /** Lo que crea un tenant es suyo; el superadmin elige el negocio en el formulario. */
    public function formBeforeCreate($model): void
    {
        if (!CurrentTenant::isAdmin()) {
            $model->tenant_id = CurrentTenant::id();
        }
    }

    /** Crea (o vincula) el usuario RainLab.User del cliente para que gestione sus citas con cuenta. */
    public function update_onCreateUser($recordId = null)
    {
        $customer = \Aero\Office\Models\Customer::visible()->findOrFail((int) $recordId);
        try {
            app(\Aero\Office\Classes\CustomerUsers::class)->ensureUser($customer);
        } catch (\Aero\Office\Classes\OfficeException $e) {
            throw new \ApplicationException($e->getMessage());
        }
        \Flash::success('Usuario vinculado. El cliente puede entrar al portal con su correo.');

        return \Redirect::refresh();
    }
}
