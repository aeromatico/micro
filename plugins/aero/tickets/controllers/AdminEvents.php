<?php namespace Aero\Tickets\Controllers;

use Aero\Tickets\Classes\ModerationService;
use Aero\Tickets\Models\Event;
use Backend\Classes\Controller;
use BackendMenu;
use Flash;

/** Supervisión global de eventos (todos los organizadores): consulta, moderación, suspensión y cancelación. */
class AdminEvents extends Controller
{
    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.tickets.admin.events', 'aero.tickets.admin.view', 'aero.tickets.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Tickets', 'tickets_admin', 'events');
    }

    protected function act(callable $fn, string $ok, string $kind = 'success')
    {
        if (!\Aero\Tickets\Classes\Admin::can('events')) {
            throw new \ApplicationException('No tienes permiso para moderar eventos.');
        }
        $fn(Event::findOrFail((int) post('id')));
        Flash::{$kind}($ok);

        return \Redirect::refresh();
    }

    public function onApprove() { return $this->act(fn ($e) => ModerationService::approve($e, post('note')), 'Evento aprobado: ya es visible y vende.'); }
    public function onReject() { return $this->act(fn ($e) => ModerationService::reject($e, (string) post('note')), 'Evento rechazado y devuelto a borrador; se avisó al organizador.'); }
    public function onSuspend() { return $this->act(fn ($e) => ModerationService::suspend($e, (string) post('note')), 'Evento suspendido: no se muestra, no vende ni permite el ingreso.', 'warning'); }
    public function onRestore() { return $this->act(fn ($e) => ModerationService::restore($e, post('note')), 'Evento restablecido.'); }
    public function onCancelEvent() { return $this->act(fn ($e) => ModerationService::cancel($e, (string) post('note')), 'Evento cancelado: órdenes anuladas y pagos marcados para devolución.', 'warning'); }
}
