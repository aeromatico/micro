<?php namespace Aero\Connector\Controllers;

use Backend\Classes\Controller;
use BackendMenu;

class ConnectorLogs extends Controller
{
    public $implement = [
        \Backend\Behaviors\ListController::class,
    ];

    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = ['aero.connector.view_logs'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Connector', 'connector', 'connectorlogs');
    }
}
