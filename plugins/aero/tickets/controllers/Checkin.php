<?php namespace Aero\Tickets\Controllers;

use Aero\Tickets\Classes\AccessControl;
use Aero\Tickets\Models\Event;
use Backend\Classes\Controller;
use BackendMenu;

/** Control de ingreso desde el panel (cámara, lector USB o código). Misma validación atómica y bitácora que el escáner móvil. */
class Checkin extends Controller
{
    public $requiredPermissions = ['aero.tickets.use', 'aero.tickets.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Tickets', 'tickets', 'checkin');
        $this->pageTitle = 'Control de ingreso';
        $this->addCss('/plugins/aero/tickets/assets/css/scanner.css?v=1');
        $this->addJs('/plugins/aero/tickets/assets/js/jsQR.js?v=1');
        $this->addJs('/plugins/aero/tickets/assets/js/scanner.js?v=1');
    }

    public function index(): void
    {
        $this->vars['events'] = Event::visible()->whereIn('status', ['published', 'ongoing'])->orderBy('starts_at')->get();
        $this->vars['eventId'] = (int) request('event');
    }

    protected function event(): Event
    {
        return Event::visible()->findOrFail((int) post('event_id'));
    }

    public function onScan()
    {
        return ['result' => AccessControl::validate((string) post('scan'), $this->event(), AccessControl::adminOperator(), in_array(post('method'), ['qr', 'code', 'manual'], true) ? post('method') : 'qr')];
    }

    public function onSearch()
    {
        return ['found' => AccessControl::search($this->event(), (string) post('q'))];
    }

    public function onStats()
    {
        $e = $this->event();

        return ['counts' => AccessControl::counts($e), 'recent' => AccessControl::recent($e)];
    }
}
