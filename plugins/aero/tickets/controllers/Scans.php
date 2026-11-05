<?php namespace Aero\Tickets\Controllers;

use Aero\Tickets\Classes\ScopesToTenant;
use Backend\Classes\Controller;
use BackendMenu;

/** Bitácora de accesos: cada lectura, con su resultado y operador. Solo consulta. */
class Scans extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class];

    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = ['aero.tickets.use', 'aero.tickets.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Tickets', 'tickets', 'scans');
    }
}
