<?php namespace Aero\Realestate\Controllers;

use Aero\Realestate\Classes\CurrentTenant;
use Aero\Realestate\Classes\ScopesToTenant;
use Backend\Classes\Controller;
use BackendMenu;

class Offers extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';
    public $requiredPermissions = ['aero.realestate.use', 'aero.realestate.agent', 'aero.realestate.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Realestate', 'realestate', 'offers');
    }
}
