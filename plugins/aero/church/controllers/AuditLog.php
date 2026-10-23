<?php namespace Aero\Church\Controllers;

use Aero\Church\Classes\ScopesToTenant;
use Backend\Classes\Controller;
use BackendMenu;

/** Historial de operaciones (solo lectura). */
class AuditLog extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class];

    public $listConfig = 'config_list.yaml';
    public $requiredPermissions = ['aero.church.use', 'aero.church.pastor', 'aero.church.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Church', 'church', 'auditlog');
    }
}
