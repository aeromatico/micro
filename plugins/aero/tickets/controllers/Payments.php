<?php namespace Aero\Tickets\Controllers;

use Aero\Tickets\Classes\PaymentService;
use Aero\Tickets\Classes\ScopesToTenant;
use Aero\Tickets\Models\Payment;
use Aero\Tickets\Models\PaymentProof;
use Backend\Classes\Controller;
use BackendMenu;
use Flash;

/** Verificación de pagos: lista, detalle con comprobantes e historial, aprobar / rechazar / reportar / reembolsar. */
class Payments extends Controller
{
    use ScopesToTenant;

    /** El rol de finanzas de la plataforma ve las órdenes/pagos de todos los organizadores. */
    protected bool $platformFinance = true;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.tickets.payments', 'aero.tickets.admin.finance', 'aero.tickets.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Tickets', 'tickets', 'payments');
    }

    protected function payment(): Payment
    {
        $q = Payment::query();
        $this->scopeToTenant($q);

        return $q->findOrFail((int) post('id'));
    }

    public function onApprove()
    {
        $p = $this->payment();
        PaymentService::approve($p, (float) str_replace(',', '.', (string) post('amount', $p->amount)), post('note'));
        Flash::success('Pago aprobado: la orden quedó confirmada y se emitieron las entradas.');

        return \Redirect::refresh();
    }

    public function onReject()
    {
        PaymentService::reject($this->payment(), (string) post('reason'));
        Flash::success('Pago rechazado. El comprador fue avisado.');

        return \Redirect::refresh();
    }

    public function onInconsistency()
    {
        PaymentService::reportInconsistency($this->payment(), (string) post('reason'));
        Flash::warning('Pago marcado como inconsistente; la orden quedó en incidencia.');

        return \Redirect::refresh();
    }

    public function onRefunded()
    {
        PaymentService::markRefunded($this->payment(), (string) post('reference'), post('note'));
        Flash::success('Reembolso registrado.');

        return \Redirect::refresh();
    }

    /** Comprobante: solo con permiso y del propio tenant. Se sirve como descarga (nunca por URL pública). */
    public function proof($id = null)
    {
        $q = PaymentProof::query();
        if (!\Aero\Tickets\Classes\CurrentTenant::isAdmin() && !\Aero\Tickets\Classes\Admin::seesAllFinance()) {
            $tid = \Aero\Tickets\Classes\CurrentTenant::id();
            $tid ? $q->where('tenant_id', $tid) : $q->whereRaw('1 = 0');
        }
        $proof = $q->findOrFail((int) $id);

        return response($proof->binary(), 200, [
            'Content-Type' => $proof->mime,
            'Content-Disposition' => 'inline; filename="' . addslashes($proof->name ?: 'comprobante') . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'",
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
