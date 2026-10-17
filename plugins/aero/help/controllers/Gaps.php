<?php namespace Aero\Help\Controllers;

use Backend\Classes\Controller;
use BackendMenu;

/** Qué busca la gente en la ayuda: las búsquedas sin resultados son contenido por escribir. */
class Gaps extends Controller
{
    public $implement = [\Backend\Behaviors\ListController::class];

    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = ['aero.help.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Help', 'help', 'gaps');
    }
}
