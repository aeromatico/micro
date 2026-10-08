<?php namespace Aero\Sites\Controllers;

use BackendMenu;
use Backend\Classes\Controller;

/**
 * Solo lectura: los registros los genera aero.sites:generate-renewals, no
 * se crean ni editan a mano.
 */
class PlanRenewals extends Controller
{
    public $implement = [
        \Backend\Behaviors\ListController::class,
    ];

    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = ['aero.sites.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Sites', 'sites', 'planrenewals');
    }
}
