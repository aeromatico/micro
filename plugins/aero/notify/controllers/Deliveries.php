<?php namespace Aero\Notify\Controllers;

use Aero\Notify\Traits\ScopesToTenant;
use Backend\Classes\Controller;
use BackendMenu;

/**
 * Registro de entregas: por qué se envió (o no) cada notificación. Solo
 * lectura por ahora — el reenvío (aero.notify.resend) queda para cuando el
 * motor soporte reintentos de verdad en vez de repetir el fire() original.
 */
class Deliveries extends Controller
{
    use ScopesToTenant;

    public $implement = [
        \Backend\Behaviors\ListController::class,
    ];

    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = ['aero.notify.view_deliveries'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Notify', 'notify', 'notify-deliveries');
    }
}
