<?php namespace Aero\Gym\Controllers;

use Aero\Gym\Classes\CurrentTenant;
use Aero\Gym\Classes\ScopesToTenant;
use Backend\Classes\Controller;
use BackendMenu;

class Schedules extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.gym.use', 'aero.gym.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Gym', 'gym', 'schedules');
    }

    /** Lo que crea un tenant es suyo; el superadmin elige el gimnasio en el formulario. */
    public function formBeforeCreate($model): void
    {
        if (!CurrentTenant::isAdmin()) {
            $model->tenant_id = CurrentTenant::id();
        }
    }
}
