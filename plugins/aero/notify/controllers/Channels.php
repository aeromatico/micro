<?php namespace Aero\Notify\Controllers;

use Aero\Notify\Models\Channel;
use Backend\Classes\Controller;
use BackendMenu;
use Flash;

/**
 * Canales propios de un tenant: credenciales/dirección de destino que
 * Notify::deliverOne() consulta para CUALQUIER evento con Rule en ese canal
 * (no solo el contacto que originó esta tabla — ver models/Channel.php).
 *
 * Reservado a manage_channels (hoy solo superadmin, igual que el resto del
 * gateway). No usa ScopesToTenant: la lista superadmin necesita ver y elegir
 * el tenant de cada canal, no operar "dentro" de uno.
 */
class Channels extends Controller
{
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
        \Backend\Behaviors\ReorderController::class,
    ];

    public $formConfig    = 'config_form.yaml';
    public $listConfig    = 'config_list.yaml';
    public $reorderConfig = 'config_reorder.yaml';

    public $requiredPermissions = ['aero.notify.manage_channels'];

    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('Aero.Notify', 'notify', 'notify-channels');
    }

    public function index_onDelete()
    {
        $checked = (array) post('checked');

        if ($checked) {
            Channel::whereIn('id', $checked)->delete();
            Flash::success(count($checked) . ' canal(es) eliminado(s).');
        }

        return $this->listRefresh();
    }
}
