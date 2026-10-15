<?php namespace Aero\Workspaces\Controllers;

use Backend\Classes\Controller;
use BackendMenu;

/** Categorías del catálogo de agentes (solo superadmin). */
class Categories extends Controller
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
        BackendMenu::setContext('Aero.Workspaces', 'workspaces', 'categories');
    }
}
