<?php namespace Aero\Sms\Controllers;

use Aero\Sms\Classes\CurrentTenant;
use Aero\Sms\Classes\ScopesToTenant;
use Backend\Classes\Controller;
use BackendMenu;

class Templates extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.sms.use', 'aero.sms.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Sms', 'sms', 'templates');
    }

    /** Lo que crea un tenant es suyo; lo que crea el superadmin es global. */
    public function formBeforeCreate($model): void
    {
        if (!CurrentTenant::isAdmin()) {
            $model->tenant_id = CurrentTenant::id();
        }
    }
}
