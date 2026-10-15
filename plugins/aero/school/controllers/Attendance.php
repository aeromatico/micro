<?php namespace Aero\School\Controllers;

use Aero\School\Classes\AttendanceService;
use Aero\School\Classes\SchoolException;
use Aero\School\Classes\TenantScreen;
use Aero\School\Models\Attendance as AttendanceModel;
use Aero\School\Models\Course;
use Aero\School\Models\Student;
use Backend\Classes\Controller;
use BackendMenu;

/** Pasar lista: curso + fecha, todos «Presente» por defecto y solo se marca la excepción. */
class Attendance extends Controller
{
    use TenantScreen;

    public $requiredPermissions = ['aero.school.use', 'aero.school.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.School', 'school', 'attendance');
        $this->pageTitle = 'Asistencia';
    }

    public function index()
    {
        $this->prepare();
    }

    protected function prepare(): void
    {
        $courses = $this->courses();
        $course = $courses->firstWhere('id', (int) request('course_id')) ?: null;
        $date = request('date') && strtotime((string) request('date')) ? \Carbon\Carbon::parse(request('date'))->toDateString() : today()->toDateString();

        $students = collect();
        $marks = collect();
        if ($course) {
            $students = Student::where('course_id', $course->id)->where('tenant_id', $course->tenant_id)->where('status', 'active')
                ->orderBy('paternal_surname')->orderBy('maternal_surname')->orderBy('first_name')->get();
            $marks = AttendanceModel::where('course_id', $course->id)->whereDate('date', $date)->get()->keyBy('student_id');
        }

        $this->vars += compact('courses', 'course', 'date', 'students', 'marks');
        $this->vars['statuses'] = AttendanceModel::STATUSES;
    }

    public function onSave()
    {
        $course = Course::where('tenant_id', $this->tenantId())->findOrFail((int) post('course_id'));

        try {
            $res = app(AttendanceService::class)->record($course, (string) post('date'), (array) post('rows', []), $this->user?->id, (bool) post('notify', 1));
        } catch (SchoolException $e) {
            throw new \ApplicationException($e->getMessage());
        }

        \Flash::success("Asistencia guardada ({$res['saved']} estudiantes)" . ($res['notified'] ? " · {$res['notified']} apoderados avisados por WhatsApp." : '.'));
    }
}
