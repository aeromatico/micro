<?php namespace Aero\Clinica\Controllers;

use Aero\Clinica\Classes\CurrentTenant;
use Aero\Clinica\Classes\ScopesToTenant;
use Backend\Classes\Controller;
use BackendMenu;

class Questionnaires extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.clinica.professional', 'aero.clinica.settings', 'aero.clinica.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Clinica', 'clinica', 'questionnaires');
    }

    public function formBeforeCreate($model): void
    {
        if (!CurrentTenant::isAdmin()) {
            $model->tenant_id = CurrentTenant::id();
        }
    }
}
