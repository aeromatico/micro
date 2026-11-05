<?php namespace Aero\Tickets\Controllers;

use Aero\Tickets\Classes\PaymentService;
use Aero\Tickets\Classes\ScopesToTenant;
use Aero\Tickets\Models\Order;
use Backend\Classes\Controller;
use BackendMenu;
use Flash;

/** Órdenes de compra: se consultan y se resuelven (confirmar cobro, incidencia, cancelar). No se crean ni se borran aquí. */
class Orders extends Controller
{
    use ScopesToTenant;

    /** El rol de finanzas de la plataforma ve las órdenes/pagos de todos los organizadores. */
    protected bool $platformFinance = true;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.tickets.use', 'aero.tickets.admin.finance', 'aero.tickets.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Tickets', 'tickets', 'orders');
    }

    protected function order(?int $id = null): Order
    {
        $q = Order::query();
        $this->scopeToTenant($q);

        return $q->findOrFail($id ?? (int) post('id'));
    }

    protected function requirePayments(): void
    {
        if (!$this->user->hasAnyAccess(['aero.tickets.payments', 'aero.tickets.admin.finance', 'aero.tickets.superadmin'])) {
            throw new \ApplicationException('No tienes permiso para verificar pagos.');
        }
    }

    /** Confirmar cobro = aprobar el pago informado o registrar uno manual (con nota); siempre deja pago e historial. */
    public function onConfirm()
    {
        $this->requirePayments();
        $p = PaymentService::confirmOrder($this->order(), post('note'));
        Flash::success($p->status === 'approved' ? 'Pago registrado y orden confirmada: las entradas ya son válidas.' : 'Pago registrado, pero la orden no se pudo reabrir: queda para reembolso.');

        return \Redirect::refresh();
    }

    public function onResend()
    {
        $o = $this->order();
        if ($o->status !== 'confirmed') {
            throw new \ApplicationException('Solo se reenvían las entradas de una orden confirmada.');
        }
        Flash::success(\Aero\Tickets\Classes\TicketDelivery::send($o, true) ? 'Entradas reenviadas a ' . $o->buyer_email . '.' : 'La orden no tiene correo o entradas para enviar.');

        return \Redirect::refresh();
    }

    public function onIncident()
    {
        $this->order()->markIncident(post('note'));
        Flash::warning('Orden en incidencia: el cupo sigue retenido hasta que la resuelvas.');

        return \Redirect::refresh();
    }

    public function onCancelOrder()
    {
        $this->order()->cancel(post('reason'));
        Flash::success('Orden cancelada y cupos liberados.');

        return \Redirect::refresh();
    }

    /** En lote solo se aprueban pagos ya informados y sin alertas; lo demás se revisa uno a uno. */
    public function onConfirmChecked()
    {
        $this->requirePayments();
        $ok = 0;
        $skipped = 0;
        foreach (array_map('intval', (array) post('checked')) as $id) {
            $order = $this->order($id);
            $pay = \Aero\Tickets\Models\Payment::where('order_id', $order->id)->where('status', 'submitted')->where('is_suspicious', false)->orderByDesc('id')->first();
            if (!$pay) {
                $skipped++;
                continue;
            }
            try {
                PaymentService::approve($pay, (float) $pay->amount, 'Aprobación en lote');
                $ok++;
            } catch (\ApplicationException $e) {
                $skipped++;
            }
        }
        Flash::success("{$ok} pago(s) aprobados." . ($skipped ? " {$skipped} requieren revisión individual (sin pago informado, sospechosos o vencidos)." : ''));

        return $this->listRefresh();
    }
}
