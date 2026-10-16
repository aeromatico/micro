<?php namespace Aero\Invoicing\Controllers;

use Aero\Invoicing\Classes\CurrentTenant;
use Aero\Invoicing\Classes\ScopesToTenant;
use Backend\Classes\Controller;
use BackendMenu;

class Issuers extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.invoicing.use', 'aero.invoicing.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Invoicing', 'invoicing', 'issuers');
    }

    /** Un emisor de tenant es del tenant; el driver y el ambiente solo los cambia el superadmin. */
    public function formBeforeCreate($model): void
    {
        if (!CurrentTenant::isAdmin()) {
            $model->tenant_id = CurrentTenant::id();
            $model->driver = 'floogos';
            $model->environment = 'test';
            $model->provider_credential = null;
        }
    }
}
