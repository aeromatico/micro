<?php namespace Aero\Church\Controllers;

use Aero\Church\Models\Prayer;
use Aero\Church\Models\PrayerNote;
use Backend\Classes\Controller;
use BackendMenu;

/** Información sensible: ver Prayer::scopeManaged(). Sin ScopesToTenant: el alcance lo define managed(). */
class Prayers extends Controller
{
    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';
    public $requiredPermissions = ['aero.church.use', 'aero.church.pastor', 'aero.church.leader', 'aero.church.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Church', 'church', 'prayers');
    }

    public function listExtendQuery($query): void
    {
        $query->whereIn('aero_church_prayers.id', Prayer::managed()->select('aero_church_prayers.id'));
    }

    public function formExtendQuery($query): void
    {
        $this->listExtendQuery($query);
    }

    /** Un responsable (líder) acompaña lo que se le asignó; registrar peticiones nuevas es de la dirección pastoral. */
    public function create($context = null)
    {
        if (\Aero\Church\Classes\PrayerAccess::restrictedUserId() !== null) {
            abort(403);
        }

        return $this->asExtension('FormController')->create($context);
    }

    public function update_onAddNote($recordId = null)
    {
        $prayer = Prayer::managed()->findOrFail((int) $recordId);
        $note = trim((string) post('note'));
        if ($note === '') {
            throw new \ApplicationException('Escribe la nota.');
        }

        PrayerNote::create(['tenant_id' => $prayer->tenant_id, 'prayer_id' => $prayer->id, 'note' => mb_substr($note, 0, 2000)]);

        \Flash::success('Nota guardada.');
        return \Redirect::refresh();
    }
}
