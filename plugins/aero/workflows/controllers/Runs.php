<?php namespace Aero\Workflows\Controllers;

use Aero\Workflows\Classes\ScopesToTenant;
use Backend\Classes\Controller;
use BackendMenu;

/** Solo lectura: el historial de ejecuciones con sus pasos. */
class Runs extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.workflows.use', 'aero.workflows.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Workflows', 'workflows', 'runs');
    }
}
