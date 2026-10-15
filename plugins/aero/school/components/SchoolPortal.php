<?php namespace Aero\School\Components;

use Aero\School\Classes\AttendanceService;
use Aero\School\Classes\Billing;
use Aero\School\Classes\GradeBook;
use Aero\School\Classes\GuardianUsers;
use Aero\School\Classes\ReportCard;
use Aero\School\Classes\SchoolException;
use Aero\School\Models\Announcement;
use Aero\School\Models\Attendance;
use Aero\School\Models\Charge;
use Aero\School\Models\Settings;
use Aero\School\Models\Student;
use Aero\Sites\Models\Tenant;
use Cms\Classes\ComponentBase;

/**
 * Portal del padre en el micrositio del colegio: cuenta de cada hijo (cuotas y
 * pagos), asistencia, notas y boletín, y comunicados. El apoderado es el usuario
 * de rainlab:user vinculado a un Guardian DEL TENANT DEL DOMINIO; cualquier otra
 * combinación no ve nada (falla cerrado). Un padre solo ve a sus propios hijos.
 */
class SchoolPortal extends ComponentBase
{
    public ?Tenant $tenant = null;
    public $user = null;

    public bool $enabled = false;
    public $guardians;
    public $children;
    public ?Student $child = null;
    public ?Settings $settings = null;

    public array $statement = [];
    public array $attendance = [];
    public array $recent = [];
    public ?array $report = null;
    public bool $gradesBlocked = false;
    public $announcements;
    public ?array $qr = null;

    public function componentDetails(): array
    {
        return ['name' => 'Colegio (portal del padre)', 'description' => 'Pagos, asistencia, notas y comunicados de los hijos.'];
    }

    public function defineProperties(): array
    {
        return ['mode' => [
            'title' => 'Modo', 'type' => 'dropdown', 'default' => 'portal',
            'options' => ['portal' => 'Portal', 'boletin' => 'Descarga de boletín', 'pagar' => 'Pago por QR'],
        ]];
    }

    public function onRun()
    {
        if (!$this->boot()) {
            return $this->controller->run('404');
        }

        $mode = $this->property('mode', 'portal');

        if ($mode === 'boletin') {
            return $this->boletin();
        }
        if ($mode === 'pagar') {
            $this->payQr();

            return null;
        }

        $this->load();
    }

    protected function boot(): bool
    {
        $this->tenant = $this->tenant ?: Tenant::resolveFromDomain(request()->getHost());
        $this->user = $this->user ?: \Auth::getUser();
        if (!$this->tenant || !Settings::isEnabled($this->tenant->id)) {
            return false;
        }

        $this->enabled = true;
        $this->settings = Settings::forTenant($this->tenant->id);
        $this->guardians = GuardianUsers::forUser($this->user, $this->tenant->id);
        $this->children = $this->guardians->isEmpty() ? collect() : Student::where('tenant_id', $this->tenant->id)
            ->whereHas('guardians', fn ($q) => $q->whereIn('aero_school_guardians.id', $this->guardians->pluck('id')))
            ->with('course')->orderBy('first_name')->get();

        return true;
    }

    /** Hijo pedido (?hijo=) solo si es uno de los suyos. */
    protected function pickChild(?int $id = null): ?Student
    {
        $id = $id ?: (int) request()->query('hijo');

        return $this->children->firstWhere('id', $id) ?: $this->children->first();
    }

    protected function load(): void
    {
        $this->child = $this->pickChild();
        if (!$this->child) {
            return;
        }

        $s = $this->child;
        $st = app(Billing::class)->statement($s);
        $this->statement = [
            'billed' => $st['billed'], 'paid' => $st['paid'], 'balance' => $st['balance'], 'overdue' => $st['overdue'],
            'charges' => $st['charges']->map(fn ($c) => [
                'id' => $c->id, 'concept' => $c->concept, 'due_on' => $c->due_on?->format('d/m/Y'), 'net' => $c->net, 'due' => $c->due,
                'status' => $c->status === 'paid' ? 'paid' : ($c->is_overdue ? 'overdue' : ($c->status === 'partial' ? 'partial' : 'pending')),
            ])->all(),
            'payments' => $st['payments']->map(fn ($p) => [
                'receipt' => str_pad((string) $p->receipt_no, 6, '0', STR_PAD_LEFT), 'date' => $p->paid_at?->format('d/m/Y'), 'amount' => (float) $p->amount,
            ])->all(),
        ];
        $this->statement['can_qr'] = class_exists(\Aero\Pay\Classes\QrIssuer::class) && (bool) $this->settings->bank_account_id;

        $this->attendance = AttendanceService::summary($s);
        $labels = Attendance::STATUSES;
        $this->recent = Attendance::where('student_id', $s->id)->where('status', '!=', 'present')->orderByDesc('date')->limit(15)->get()
            ->map(fn ($a) => ['date' => \Carbon\Carbon::parse($a->date)->translatedFormat('D d/m'), 'status' => $a->status, 'label' => $labels[$a->status] ?? $a->status, 'note' => $a->note])->all();

        $this->gradesBlocked = !$this->settings->portal_grades
            || ($this->settings->portal_block_if_debt && $st['overdue'] > 0);
        if (!$this->gradesBlocked && $s->course_id) {
            try {
                $r = app(GradeBook::class)->report($s);
                $this->report = [
                    'periods' => $r['periods'], 'qualitative' => $r['qualitative'], 'passing' => $r['passing'], 'rows' => $r['rows'],
                    'average' => $r['average'], 'result' => $r['result'], 'course' => $s->course->name,
                ];
            } catch (SchoolException $e) {
                $this->report = null;
            }
        }

        $this->announcements = Announcement::where('tenant_id', $s->tenant_id)->where('status', 'sent')
            ->where(fn ($q) => $q->where('audience', 'all')
                ->orWhere(fn ($w) => $w->where('audience', 'course')->where('course_id', $s->course_id))
                ->orWhere(fn ($w) => $w->where('audience', 'level')->where('level', $s->course?->level)))
            ->orderByDesc('sent_at')->limit(20)->get();
    }

    protected function boletin()
    {
        $student = $this->children->firstWhere('id', (int) $this->param('student'));
        if (!$student || !$this->settings->portal_grades) {
            return $this->controller->run('404');
        }
        if ($this->settings->portal_block_if_debt && $student->overdueBalance() > 0) {
            return \Redirect::to('/colegio?hijo=' . $student->id)->with('flash', 'Regularice su deuda para descargar el boletín.');
        }

        $card = new ReportCard();
        try {
            $pdf = $card->pdf($student);
            $html = $pdf === null ? $card->html($student) : null;
        } catch (SchoolException $e) {
            return $this->controller->run('404');
        }

        if ($pdf !== null) {
            return response($pdf, 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . ReportCard::filename($student) . '"',
            ]);
        }

        return response($html);
    }

    /** QR de una cuota del hijo (se genera al abrir la página). */
    protected function payQr(): void
    {
        $charge = Charge::where('tenant_id', $this->tenant->id)->find((int) $this->param('charge'));
        $student = $charge ? $this->children->firstWhere('id', $charge->student_id) : null;
        $this->child = $student;
        if (!$charge || !$student) {
            $this->qr = null;

            return;
        }

        $qr = app(Billing::class)->issueQr($charge->load('student'));
        $this->qr = [
            'concept' => $charge->concept, 'amount' => $charge->due, 'student' => $student->full_name,
            'image' => $qr ? url('/api/v1/pay/public/qr/' . $qr->internal_reference . '/image') : null,
            'paid' => $charge->status === 'paid',
        ];
    }
}
