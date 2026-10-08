<?php namespace Aero\Sites\Controllers;

use BackendMenu;
use Backend\Classes\Controller;

/**
 * Leads del negocio de la plataforma (Trial / Gran Empresa), capturados por
 * el componente platformLeadForm en el landing (themes/master). No son de un
 * tenant — solo superadmin los ve.
 */
class PlatformLeads extends Controller
{
    public $implement = [
        \Backend\Behaviors\ListController::class,
    ];

    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = ['aero.sites.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Sites', 'sites', 'platformleads');
    }
}
