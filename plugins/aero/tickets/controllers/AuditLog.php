<?php namespace Aero\Tickets\Controllers;

use Backend\Classes\Controller;
use BackendMenu;
use Flash;

class AuditLog extends Controller
{
    public $implement = [\Backend\Behaviors\ListController::class];

    public $listConfig = 'config_list.yaml';


    public $requiredPermissions = ['aero.tickets.admin.view', 'aero.tickets.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Tickets', 'tickets_admin', 'audit');
    }
}
