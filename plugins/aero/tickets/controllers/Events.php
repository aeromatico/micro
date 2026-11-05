<?php namespace Aero\Tickets\Controllers;

use Aero\Tickets\Classes\ScopesToTenant;
use Aero\Tickets\Models\Event;
use Backend\Classes\Controller;
use BackendMenu;
use Flash;

class Events extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.tickets.use', 'aero.tickets.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Tickets', 'tickets', 'events');
    }

    /** Solo eventos del tenant (falla cerrado) para acciones AJAX. */
    protected function findEvent(): Event
    {
        $q = Event::query();
        $this->scopeToTenant($q);

        return $q->findOrFail((int) post('id'));
    }

    public function onPublish()
    {
        $this->findEvent()->publish();
        Flash::success('Evento publicado.');

        return \Redirect::refresh();
    }

    public function onUnpublish()
    {
        $this->findEvent()->unpublish();
        Flash::success('El evento volvió a borrador.');

        return \Redirect::refresh();
    }

    public function onCancelEvent()
    {
        $this->findEvent()->cancel();
        Flash::success('Evento cancelado.');

        return \Redirect::refresh();
    }
}
