<?php namespace Aero\Help\Controllers;

use Backend\Classes\Controller;
use BackendMenu;

class Topics extends Controller
{
    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.help.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Help', 'help', 'topics');
    }
}
