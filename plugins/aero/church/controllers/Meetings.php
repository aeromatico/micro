<?php namespace Aero\Church\Controllers;

use Aero\Church\Models\Attendance;
use Aero\Church\Models\Meeting;
use Backend\Classes\Controller;
use BackendMenu;

class Meetings extends Controller
{
    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';
    public $requiredPermissions = ['aero.church.use', 'aero.church.pastor', 'aero.church.secretary', 'aero.church.leader', 'aero.church.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Church', 'church', 'meetings');
    }

    public function listExtendQuery($query): void
    {
        $query->whereIn('aero_church_meetings.id', Meeting::managed()->select('aero_church_meetings.id'));
    }

    public function formExtendQuery($query): void
    {
        $this->listExtendQuery($query);
    }

    /** Guarda la asistencia: marcados = presentes, el resto de integrantes vigentes = ausentes. */
    public function update_onSaveAttendance($recordId = null)
    {
        $meeting = Meeting::managed()->with('group')->findOrFail((int) $recordId);
        $present = array_map('intval', (array) post('present', []));
        $validIds = $meeting->group->activeMembers()->pluck('member_id')->all();

        foreach ($validIds as $memberId) {
            $a = Attendance::firstOrNew(['meeting_id' => $meeting->id, 'member_id' => $memberId]);
            $a->tenant_id = $meeting->tenant_id;
            $a->present = in_array($memberId, $present, true);
            $a->save();
        }

        \Flash::success('Asistencia guardada.');
        return \Redirect::refresh();
    }
}
