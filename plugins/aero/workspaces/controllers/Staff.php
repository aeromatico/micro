<?php namespace Aero\Workspaces\Controllers;

use Backend\Classes\Controller;
use BackendMenu;

/**
 * Catálogo de staff para el superadmin: prompt de sistema, modelo, tarifas y skills.
 */
class Staff extends Controller
{
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = ['aero.workspaces.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Workspaces', 'workspaces', 'staff');
    }
}
