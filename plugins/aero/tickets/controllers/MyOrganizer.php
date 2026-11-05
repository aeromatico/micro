<?php namespace Aero\Tickets\Controllers;

use Aero\Tickets\Classes\CurrentTenant;
use Aero\Tickets\Classes\OrganizerService;
use Aero\Tickets\Models\Organizer;
use Backend\Classes\Controller;
use BackendMenu;

/** El organizador completa sus datos y ve el estado de su aprobación. */
class MyOrganizer extends Controller
{
    public $implement = [\Backend\Behaviors\FormController::class];

    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.tickets.use', 'aero.tickets.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Tickets', 'tickets', 'my_organizer');
    }

    public function index()
    {
        $tid = CurrentTenant::id();
        if (!$tid) {
            \Flash::error('No hay un espacio de trabajo activo.');

            return \Backend::redirect('aero/tickets/events');
        }

        return \Backend::redirect('aero/tickets/myorganizer/update/' . OrganizerService::ensure($tid)->id);
    }

    public function formExtendQuery($query): void
    {
        $tid = CurrentTenant::id();
        $tid ? $query->where('tenant_id', $tid) : $query->whereRaw('1 = 0');   // falla cerrado: solo el propio
    }

    /** Guardar pasa por el servicio: si estaba rechazado, vuelve a revisión. */
    public function update_onSave($recordId = null, $context = null)
    {
        $o = Organizer::where('tenant_id', CurrentTenant::id())->findOrFail((int) $recordId);
        OrganizerService::submit($o, array_intersect_key((array) post('Organizer'), array_flip($o->fillable)));
        \Flash::success($o->fresh()->status === 'pending' ? 'Datos enviados. Te avisaremos cuando se revisen.' : 'Datos guardados.');

        return \Backend::redirect('aero/tickets/myorganizer');
    }
}
