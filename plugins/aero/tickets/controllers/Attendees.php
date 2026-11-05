<?php namespace Aero\Tickets\Controllers;

use Aero\Tickets\Classes\ScopesToTenant;
use Backend\Classes\Controller;
use BackendMenu;

class Attendees extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.tickets.use', 'aero.tickets.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Tickets', 'tickets', 'attendees');
    }

    /** Alta manual del organizador: pasa por el emisor (aforo, cupos y cortesías cuentan). */
    public function create_onSave($context = null)
    {
        $d = (array) post('Ticket');
        $event = \Aero\Tickets\Models\Event::visible()->findOrFail((int) ($d['event_id'] ?? 0));
        $type = \Aero\Tickets\Models\TicketType::where('event_id', $event->id)->findOrFail((int) ($d['ticket_type_id'] ?? 0));

        $tickets = \Aero\Tickets\Classes\TicketIssuer::issue($event, $type, [
            'name' => $d['holder_name'] ?? '', 'email' => $d['holder_email'] ?? null,
            'phone' => $d['holder_phone'] ?? null, 'document' => $d['holder_document'] ?? null,
        ], max(1, min(50, (int) ($d['quantity'] ?? 1))), 'manual');

        \Flash::success($tickets ? count($tickets) . ' entrada(s) emitida(s).' : 'Orden creada y pendiente de pago: las entradas se emiten cuando confirmes el cobro en «Órdenes de compra».');

        return \Backend::redirect('aero/tickets/attendees');
    }

    /** PDF de una entrada (solo del propio tenant). */
    public function pdf($id = null)
    {
        $q = \Aero\Tickets\Models\Ticket::query();
        $this->scopeToTenant($q);
        $t = $q->findOrFail((int) $id);
        $pdf = \Aero\Tickets\Classes\TicketRenderer::pdf([$t]);
        abort_unless($pdf, 404);

        return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="' . \Aero\Tickets\Classes\TicketRenderer::filename($t) . '"', 'Cache-Control' => 'private, no-store']);
    }

    /** Reenvía por correo las entradas de las órdenes de las entradas seleccionadas (al correo de la compra). */
    public function onResend()
    {
        $n = 0;
        foreach ($this->checked()->get()->pluck('order_id')->filter()->unique() as $orderId) {
            $order = \Aero\Tickets\Models\Order::find($orderId);
            try {
                if ($order && $order->status === 'confirmed' && \Aero\Tickets\Classes\TicketDelivery::send($order, true)) {
                    $n++;
                }
            } catch (\Throwable $e) {
                \Log::warning('aero.tickets: reenvío fallido: ' . $e->getMessage());
            }
        }
        \Flash::success($n ? "Entradas reenviadas ({$n} orden(es))." : 'Nada para reenviar: las órdenes deben estar confirmadas y tener correo.');

        return $this->listRefresh();
    }

    protected function checked()
    {
        $q = \Aero\Tickets\Models\Ticket::query();
        $this->scopeToTenant($q);

        return $q->whereIn('id', array_map('intval', (array) post('checked')));
    }

    /** Aprobar: aprueba el pago informado de la orden de cada entrada (verifica antes el dinero en tu banco). */
    public function onMarkPaid()
    {
        if (!$this->user->hasAnyAccess(['aero.tickets.payments', 'aero.tickets.superadmin'])) {
            throw new \ApplicationException('No tienes permiso para verificar pagos.');
        }
        $n = 0;
        $skipped = 0;
        $tickets = $this->checked()->get();
        foreach ($tickets->pluck('order_id')->filter()->unique() as $orderId) {
            $pay = \Aero\Tickets\Models\Payment::where('order_id', $orderId)->where('status', 'submitted')->where('is_suspicious', false)->orderByDesc('id')->first();
            if (!$pay) {
                $skipped++;
                continue;
            }
            try {
                \Aero\Tickets\Classes\PaymentService::approve($pay, (float) $pay->amount, 'Aprobación desde Asistentes');
                $n++;
            } catch (\ApplicationException $e) {
                $skipped++;
            }
        }
        // Inscripciones restringidas gratuitas: se aprueban sin cobro.
        foreach ($tickets->pluck('order_id')->filter()->unique() as $orderId) {
            $order = \Aero\Tickets\Models\Order::where('id', $orderId)->where('status', 'verifying')->where('total', 0)->first();
            if ($order) {
                try { $order->confirm(); $n++; $skipped = max(0, $skipped - 1); } catch (\ApplicationException $e) {}
            }
        }
        \Flash::success("{$n} orden(es) aprobadas." . ($skipped ? " {$skipped} requieren revisión en Pagos." : ''));

        return $this->listRefresh();
    }

    public function onVoid()
    {
        $n = $this->checked()->whereIn('status', ['pending', 'reserved', 'valid'])->update(['status' => 'cancelled']);
        \Flash::success("{$n} entrada(s) anuladas.");

        return $this->listRefresh();
    }
}
