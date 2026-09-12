<?php namespace Aero\Credits\Controllers;

use Backend\Classes\Controller;
use BackendMenu;

class CreditTransactions extends Controller
{
    public $implement = [
        \Backend\Behaviors\ListController::class,
    ];

    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = ['aero.credits.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Credits', 'credits', 'credittransactions');
    }
}
