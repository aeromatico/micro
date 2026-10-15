<?php namespace Aero\School\Controllers;

use Aero\School\Classes\GradeBook as Grades;
use Aero\School\Classes\ReportCard;
use Aero\School\Classes\SchoolException;
use Aero\School\Classes\TenantScreen;
use Aero\School\Models\Course;
use Aero\School\Models\Grade;
use Aero\School\Models\Student;
use Aero\School\Models\Subject;
use Backend\Classes\Controller;
use BackendMenu;

/** Planilla de notas: curso → área → trimestre, con el total calculado al escribir. */
class Gradebook extends Controller
{
    use TenantScreen;

    public $requiredPermissions = ['aero.school.use', 'aero.school.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.School', 'school', 'gradebook');
        $this->pageTitle = 'Calificaciones';
    }

    public function index()
    {
        $courses = $this->courses();
        $course = $courses->firstWhere('id', (int) request('course_id')) ?: null;
        $subjects = $course ? $course->subjects()->orderBy('sort_order')->orderBy('id')->get() : collect();
        $subject = $subjects->firstWhere('id', (int) request('subject_id')) ?: null;
        $periods = $course ? Grades::periodsFor($course) : 3;
        $period = min(max(1, (int) (request('period') ?: 1)), $periods);

        $students = collect();
        $grades = collect();
        if ($course && $subject) {
            $students = Student::where('course_id', $course->id)->where('tenant_id', $course->tenant_id)->where('status', 'active')
                ->orderBy('paternal_surname')->orderBy('maternal_surname')->orderBy('first_name')->get();
            $grades = Grade::where('subject_id', $subject->id)->where('period', $period)->get()->keyBy('student_id');
        }

        $this->vars += compact('courses', 'course', 'subjects', 'subject', 'periods', 'period', 'students', 'grades');
        $this->vars['max'] = Grade::MAX;
        $this->vars['periodNames'] = Grades::PERIOD_NAMES;
    }

    public function onSave()
    {
        $course = Course::where('tenant_id', $this->tenantId())->findOrFail((int) post('course_id'));
        $subject = Subject::where('tenant_id', $course->tenant_id)->findOrFail((int) post('subject_id'));

        try {
            $n = app(Grades::class)->save($course, $subject, (int) post('period'), (array) post('rows', []));
        } catch (SchoolException $e) {
            throw new \ApplicationException($e->getMessage());
        }

        \Flash::success("Notas guardadas ({$n} estudiantes).");
    }

    /** Boletín en PDF de un estudiante (si no hay dompdf, versión HTML imprimible). */
    public function reportcard($id = null)
    {
        $student = $this->findStudent($id);
        $card = new ReportCard();

        try {
            $pdf = $card->pdf($student);
            $html = $pdf === null ? $card->html($student) : null;
        } catch (SchoolException $e) {
            throw new \ApplicationException($e->getMessage());
        }

        if ($pdf !== null) {
            return response($pdf, 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . ReportCard::filename($student) . '"',
            ]);
        }

        return response($html);
    }

    /** Boletines de todo el curso en un solo PDF. */
    public function coursecards($courseId = null)
    {
        $course = Course::where('tenant_id', $this->tenantId())->findOrFail($courseId);
        $students = Student::where('course_id', $course->id)->where('status', 'active')
            ->orderBy('paternal_surname')->orderBy('maternal_surname')->orderBy('first_name')->get();
        $card = new ReportCard();
        $pdf = $card->pdfMany($students);

        if ($pdf === null) {
            return response($card->htmlMany($students));
        }

        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="boletines-' . \Illuminate\Support\Str::slug($course->name) . '.pdf"',
        ]);
    }
}
