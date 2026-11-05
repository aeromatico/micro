<?php namespace Aero\Tickets\Controllers;

use Aero\Tickets\Classes\OrganizerService;
use Aero\Tickets\Models\Organizer;
use Backend\Classes\Controller;
use BackendMenu;
use Flash;

/** Solicitudes y gestión de organizadores: aprobar, rechazar, suspender y reactivar, con historial. */
class Organizers extends Controller
{
    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.tickets.admin.organizers', 'aero.tickets.admin.view', 'aero.tickets.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Tickets', 'tickets_admin', 'organizers');
    }

    protected function act(callable $fn, string $ok)
    {
        if (!\Aero\Tickets\Classes\Admin::can('organizers')) {
            throw new \ApplicationException('No tienes permiso para gestionar organizadores.');
        }
        $fn(Organizer::findOrFail((int) post('id')));
        Flash::success($ok);

        return \Redirect::refresh();
    }

    public function onApprove() { return $this->act(fn ($o) => OrganizerService::approve($o, post('note')), 'Organizador aprobado y avisado.'); }
    public function onReject() { return $this->act(fn ($o) => OrganizerService::reject($o, (string) post('note')), 'Solicitud rechazada y organizador avisado.'); }
    public function onSuspend() { return $this->act(fn ($o) => OrganizerService::suspend($o, (string) post('note')), 'Organizador suspendido: sus eventos dejan de vender y de mostrarse.'); }
    public function onReinstate() { return $this->act(fn ($o) => OrganizerService::reinstate($o, post('note')), 'Organizador reactivado.'); }
}
