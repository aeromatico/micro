<?php namespace Aero\Tickets\Controllers;

use Backend\Classes\Controller;
use BackendMenu;

/** Libro de comisiones generadas (una por orden cobrada). Solo consulta; base de las liquidaciones futuras. */
class CommissionEntries extends Controller
{
    public $implement = [\Backend\Behaviors\ListController::class];

    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = ['aero.tickets.admin.finance', 'aero.tickets.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Tickets', 'tickets_admin', 'commission_entries');
    }
}
