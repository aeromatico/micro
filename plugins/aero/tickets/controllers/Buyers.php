<?php namespace Aero\Tickets\Controllers;

use Aero\Tickets\Models\Buyer;
use Backend\Classes\Controller;
use BackendMenu;
use Flash;

/** Compradores sin cuenta: perfil, historial y bloqueo para impedir nuevas compras. */
class Buyers extends Controller
{
    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.tickets.admin.organizers', 'aero.tickets.admin.view', 'aero.tickets.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Tickets', 'tickets_admin', 'buyers');
    }

    protected function manage(): Buyer
    {
        if (!\Aero\Tickets\Classes\Admin::can('organizers')) {
            throw new \ApplicationException('No tienes permiso para gestionar compradores.');
        }

        return Buyer::findOrFail((int) post('id'));
    }

    public function onBlock()
    {
        $this->manage()->block((string) post('reason'));
        Flash::warning('Comprador bloqueado: no puede hacer nuevas compras.');

        return \Redirect::refresh();
    }

    public function onUnblock()
    {
        $this->manage()->unblock(post('note'));
        Flash::success('Comprador desbloqueado.');

        return \Redirect::refresh();
    }
}
