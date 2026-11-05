<?php namespace Aero\Tickets\Controllers;

use Backend\Classes\Controller;
use BackendMenu;

/** Condiciones comerciales por organizador o por evento (la base de la plataforma está en Parámetros; el tipo de entrada manda sobre todo). */
class CommissionRules extends Controller
{
    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.tickets.admin.finance', 'aero.tickets.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Tickets', 'tickets_admin', 'commission_rules');
    }
}
