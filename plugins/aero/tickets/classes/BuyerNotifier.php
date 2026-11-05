<?php namespace Aero\Tickets\Classes;

use Aero\Tickets\Models\Event;
use Aero\Tickets\Models\Order;
use Aero\Tickets\Models\Payment;

/** Avisos por correo al comprador (sin cuenta). Nunca rompe el flujo de pago: si el correo falla, solo se registra. */
class BuyerNotifier
{
    /** Avisos intentados en este proceso (permite verificar el envío en pruebas). */
    public static array $log = [];

    protected static function url(Order $o, Event $e): string
    {
        return $e->baseUrl() . '/orden/' . $o->code;
    }

    public static function paymentRejected(Payment $p, Order $o): void
    {
        $e = Event::withTrashed()->find($o->event_id);
        $retry = $o->fresh()->status === 'pending_payment' ? '<p>Puedes volver a intentarlo desde tu orden.</p>' : '';
        self::mail($o, 'payment_rejected', ['buyer_name' => $o->buyer_name, 'event_name' => $e->name, 'reason' => (string) $p->reject_reason, 'retry_html' => $retry, 'order_url' => self::url($o, $e)]);
    }

    public static function refundPending(Payment $p, Order $o): void
    {
        $e = Event::withTrashed()->find($o->event_id);
        self::mail($o, 'refund_pending', ['buyer_name' => $o->buyer_name, 'event_name' => $e->name, 'amount' => Currency::format($p->amount_received ?? $p->amount, $p->currency), 'reason' => (string) $p->flag_reason, 'order_url' => self::url($o, $e)]);
    }

    protected static function mail(Order $o, string $code, array $vars): void
    {
        if (!$o->buyer_email) {
            return;
        }
        $r = Templates::render($code, $vars);
        if (!$r) {
            return;                                 // plantilla desactivada por el administrador
        }
        self::$log[] = ['to' => $o->buyer_email, 'subject' => $r['subject']];
        try {
            \Mail::html($r['html'], fn ($m) => $m->to($o->buyer_email, $o->buyer_name)->subject($r['subject']));
        } catch (\Throwable $e) {
            \Log::warning('aero.tickets: no se pudo avisar al comprador de la orden ' . $o->code . ': ' . $e->getMessage());
        }
    }
}
