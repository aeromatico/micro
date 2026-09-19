<?php namespace Aero\Sms\Controllers;

use Backend\Classes\Controller;
use BackendMenu;

class Optouts extends Controller
{
    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.sms.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Sms', 'sms', 'optouts');
    }
}
