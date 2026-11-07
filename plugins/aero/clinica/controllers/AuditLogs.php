<?php namespace Aero\Clinica\Controllers;

use Aero\Clinica\Classes\ScopesToTenant;
use Backend\Classes\Controller;
use BackendMenu;

/** Solo lectura: la bitácora no se edita ni se borra desde ninguna pantalla. */
class AuditLogs extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class];

    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = ['aero.clinica.settings', 'aero.clinica.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Clinica', 'clinica', 'auditlogs');
    }
}
