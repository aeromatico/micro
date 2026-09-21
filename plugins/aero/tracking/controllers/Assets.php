<?php namespace Aero\Tracking\Controllers;

use Aero\Tracking\Classes\CurrentTenant;
use Aero\Tracking\Classes\ScopesToTenant;
use Backend\Classes\Controller;
use BackendMenu;

class Assets extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.tracking.use', 'aero.tracking.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Tracking', 'tracking', 'assets');
    }

    /** Un tenant crea solo para sí; el superadmin elige el tenant en el formulario. */
    public function formBeforeCreate($model): void
    {
        if (!CurrentTenant::isAdmin()) {
            $model->tenant_id = CurrentTenant::id();
        }
    }
}
