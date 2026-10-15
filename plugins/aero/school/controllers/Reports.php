<?php namespace Aero\School\Controllers;

use Aero\School\Classes\Collections;
use Aero\School\Classes\Reports as ReportsService;
use Aero\School\Classes\TenantScreen;
use Aero\School\Models\Charge;
use Aero\School\Models\Course;
use Aero\School\Models\Settings;
use Aero\School\Models\Student;
use Aero\School\Models\Year;
use Backend\Classes\Controller;
use BackendMenu;

class Reports extends Controller
{
    use TenantScreen;

    public $requiredPermissions = ['aero.school.use', 'aero.school.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.School', 'school', 'reports');
        $this->pageTitle = 'Reportes';
    }

    public function index()
    {
        $t = $this->tenantId();
        $svc = new ReportsService($t);
        $tab = in_array(request('r'), ['morosidad', 'cursos', 'ingresos'], true) ? request('r') : 'morosidad';
        $courseId = (int) request('course_id') ?: null;

        $data = match ($tab) {
            'cursos'   => $svc->byCourse(Year::currentFor($t)?->id),
            'ingresos' => $svc->incomeByMonth(12),
            default    => $svc->delinquency($courseId),
        };

        if (request('export') === 'csv') {
            return $this->csv($tab, $data);
        }

        $this->vars += ['tab' => $tab, 'data' => $data, 'courseId' => $courseId, 'courses' => $this->courses(), 'aging' => $svc->aging(), 'kpis' => $svc->kpis()];
    }

    protected function csv(string $tab, array $rows)
    {
        $out = fopen('php://temp', 'w+');
        fwrite($out, "\xEF\xBB\xBF");
        if ($rows) {
            fputcsv($out, array_keys($rows[0]), ';');
            foreach ($rows as $r) {
                fputcsv($out, $r, ';');
            }
        }
        rewind($out);

        return response(stream_get_contents($out), 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="colegio-' . $tab . '-' . date('Ymd') . '.csv"',
        ]);
    }

    /** Alerta de cobranza masiva: un recordatorio de mora por WhatsApp a cada apoderado de los morosos (del curso o todos). */
    public function onRemindAll()
    {
        $t = $this->tenantId();
        $settings = Settings::forTenant($t);
        $courseId = (int) post('course_id') ?: null;
        $sent = 0;
        $skipped = 0;
        $collections = app(Collections::class);

        $studentIds = Charge::where('tenant_id', $t)->overdue()->distinct()->pluck('student_id');
        Student::where('tenant_id', $t)->whereIn('id', $studentIds)->where('status', 'active')
            ->when($courseId, fn ($q) => $q->where('course_id', $courseId))->with('guardians')->get()
            ->each(function (Student $s) use ($collections, $settings, &$sent, &$skipped) {
                $charges = Charge::where('student_id', $s->id)->overdue()->get()->all();
                $collections->notify($s, $settings, 'overdue', $charges, $settings->overdue_template) ? $sent++ : $skipped++;
            });

        \Flash::success("Recordatorios enviados: {$sent}." . ($skipped ? " Sin celular o sin WhatsApp: {$skipped}." : ''));

        return \Redirect::refresh();
    }
}
