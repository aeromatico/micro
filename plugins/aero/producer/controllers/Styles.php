<?php namespace Aero\Producer\Controllers;

use Backend\Classes\Controller;
use BackendMenu;

/** Catálogo de estilos de la plataforma (solo superadmin). */
class Styles extends Controller
{
    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.producer.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Producer', 'producer', 'styles');
    }
}
