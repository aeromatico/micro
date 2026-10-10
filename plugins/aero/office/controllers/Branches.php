<?php namespace Aero\Office\Controllers;

use Aero\Office\Classes\CurrentTenant;
use Aero\Office\Classes\ScopesToTenant;
use Backend\Classes\Controller;
use BackendMenu;

class Branches extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.office.use', 'aero.office.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Office', 'office', 'branches');
    }

    /** Lo que crea un tenant es suyo; el superadmin elige el negocio en el formulario. */
    public function formBeforeCreate($model): void
    {
        if (!CurrentTenant::isAdmin()) {
            $model->tenant_id = CurrentTenant::id();
        }
    }
}
