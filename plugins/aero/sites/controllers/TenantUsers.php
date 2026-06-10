<?php namespace Aero\Sites\Controllers;

use BackendMenu;
use Backend\Classes\Controller;

/**
 * Gestión de usuarios asignados a tenants.
 * Accesible desde el perfil de usuario en RainLab.User y desde la vista de Tenants.
 */
class TenantUsers extends Controller
{
    public $implement = [
        \Backend\Behaviors\ListController::class,
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\RelationController::class,
    ];

    public $listConfig   = 'config_list.yaml';
    public $formConfig   = 'config_form.yaml';
    public $relationConfig = 'config_relation.yaml';

    public $requiredPermissions = ['aero.sites.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Sites', 'sites', 'tenants');
    }
}
