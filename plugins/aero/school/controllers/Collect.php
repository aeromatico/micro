<?php namespace Aero\School\Controllers;

use Aero\School\Classes\Billing;
use Aero\School\Classes\Collections;
use Aero\School\Classes\Notifier;
use Aero\School\Classes\SchoolException;
use Aero\School\Classes\TenantScreen;
use Aero\School\Models\Charge;
use Aero\School\Models\Guardian;
use Aero\School\Models\Settings;
use Aero\School\Models\Student;
use Backend\Classes\Controller;
use BackendMenu;

/** Caja: buscar al estudiante, ver su estado de cuenta y registrar el pago. */
class Collect extends Controller
{
    use TenantScreen;

    public $requiredPermissions = ['aero.school.use', 'aero.school.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.School', 'school', 'collect');
        $this->pageTitle = 'Cobrar';
    }

    public function index()
    {
        $term = trim((string) request('q'));
        $this->vars['term'] = $term;
        $this->vars['results'] = $term === '' ? collect() : Student::where('tenant_id', $this->tenantId())->search($term)
            ->with('course')->orderBy('paternal_surname')->limit(25)->get();
        $this->vars['debtors'] = (new \Aero\School\Classes\Reports($this->tenantId()))->delinquency();
    }

    public function student($id = null)
    {
        $student = $this->findStudent($id)->load('course', 'guardians');
        $this->pageTitle = 'Cobrar — ' . $student->full_name;
        $this->vars['student'] = $student;
        $this->vars['statement'] = app(Billing::class)->statement($student);
        $this->vars['settings'] = Settings::forTenant($student->tenant_id);
        $this->vars['methods'] = \Aero\School\Models\Payment::METHODS;
    }

    public function student_onPay($id = null)
    {
        $student = $this->findStudent($id);
        $ids = array_map('intval', (array) post('charges', []));
        if (!$ids) {
            throw new \ApplicationException('Marque al menos una cuota.');
        }

        $guardian = ($gid = (int) post('guardian_id')) ? Guardian::where('tenant_id', $student->tenant_id)->find($gid) : null;

        try {
            $payment = app(Billing::class)->recordPayment(
                $student, (float) str_replace(',', '.', (string) post('amount')), (string) post('method', 'cash'), $ids,
                trim((string) post('reference')) ?: null, $guardian, trim((string) post('notes')) ?: null, $this->user?->id,
            );
        } catch (SchoolException $e) {
            throw new \ApplicationException($e->getMessage());
        }

        \Flash::success('Pago registrado: recibo N° ' . $payment->receipt_no);

        return \Backend::redirect('aero/school/payments/receipt/' . $payment->id);
    }

    /** Envía por WhatsApp el QR de cobro de una cuota al apoderado financiero. */
    public function student_onSendQr($id = null)
    {
        $student = $this->findStudent($id);
        $charge = Charge::where('student_id', $student->id)->findOrFail((int) post('charge_id'));
        $qr = app(Billing::class)->issueQr($charge->load('student'));
        if (!$qr) {
            throw new \ApplicationException('No se pudo generar el QR: configure la cuenta de cobro en Configuración (requiere Aero Pay).');
        }

        $media = url('/api/v1/pay/public/qr/' . $qr->internal_reference . '/image');
        $sent = 0;
        foreach ($student->financialGuardians() as $g) {
            $body = "Hola {$g->name}, puede pagar «{$charge->concept}» de {$student->full_name} (Bs. " . number_format($charge->due, 2) . ") escaneando este QR. ¡Gracias!";
            $sent += Notifier::whatsapp($student->tenant_id, $g->phone, $body, $g->name, $media) ? 1 : 0;
        }

        $sent ? \Flash::success("QR enviado a {$sent} apoderado(s).") : \Flash::warning('No se pudo enviar: el apoderado no tiene celular o WhatsApp no está disponible.');

        return \Redirect::refresh();
    }

    /** Recordatorio inmediato con todo lo vencido (ignora el registro de avisos automáticos). */
    public function student_onRemind($id = null)
    {
        $student = $this->findStudent($id);
        $charges = Charge::where('student_id', $student->id)->overdue()->get()->all();
        if (!$charges) {
            throw new \ApplicationException('El estudiante no tiene cuotas vencidas.');
        }

        $ok = app(Collections::class)->notify($student, Settings::forTenant($student->tenant_id), 'overdue', $charges, Settings::forTenant($student->tenant_id)->overdue_template);
        $ok ? \Flash::success('Recordatorio enviado por WhatsApp.') : \Flash::warning('No se pudo enviar: el apoderado no tiene celular o WhatsApp no está disponible.');

        return \Redirect::refresh();
    }
}
