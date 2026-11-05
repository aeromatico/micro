<?php namespace Aero\Tickets\Classes;

use Aero\Tickets\Models\Event;
use Aero\Tickets\Models\Order;
use Aero\Tickets\Models\Ticket;
use Illuminate\Support\Facades\DB;

/**
 * Envío de las entradas por correo (enlace privado de cada una + PDF adjunto).
 * El envío automático ocurre una sola vez por orden aunque se repita la confirmación; el reenvío es explícito.
 */
class TicketDelivery
{
    /** Envíos intentados en este proceso (verificable en pruebas). */
    public static array $log = [];

    /** Al confirmarse una orden: se envía después de guardar los cambios y nunca rompe el flujo. */
    public static function auto(Order $o): void
    {
        $id = $o->id;
        DB::afterCommit(function () use ($id) {
            try {
                if ($order = Order::find($id)) {
                    self::send($order, false);
                }
            } catch (\Throwable $e) {
                \Log::warning('aero.tickets: no se pudieron enviar las entradas de la orden ' . $id . ': ' . $e->getMessage());
            }
        });
    }

    /** @return bool true si se envió algo */
    public static function send(Order $o, bool $force = true): bool
    {
        if (!$o->buyer_email || ($o->tickets_sent_at && !$force)) {
            return false;
        }
        $tickets = Ticket::where('order_id', $o->id)->where('status', 'valid')->orderBy('seq')->get();
        if ($tickets->isEmpty()) {
            return false;
        }

        $e = Event::withTrashed()->find($o->event_id);
        $pdf = TicketRenderer::pdf($tickets);
        $links = '';
        foreach ($tickets as $t) {
            $links .= '<li><a href="' . e($t->public_url) . '">' . e($t->holder_name) . '</a> — código ' . e($t->code) . '</li>';
        }
        $paid = (float) $o->total > 0 ? '<p>Confirmamos tu pago de <strong>' . e(Currency::format($o->total, $o->currency)) . '</strong>.</p>' : '';
        $mail = Templates::render('ticket_delivery', [
            'event_name' => $e->name, 'buyer_name' => $o->buyer_name, 'when' => (string) $e->starts_at?->translatedFormat('l d \d\e F, H:i'),
            'where' => trim(implode(', ', array_filter([$e->venue_name, $e->address, $e->city]))), 'payment_note_html' => $paid, 'tickets_html' => $links,
            'pdf_note_html' => $pdf ? '<p>También las adjuntamos en PDF.</p>' : '', 'order_url' => $e->baseUrl() . '/orden/' . $o->code,
        ]);

        self::$log[] = ['to' => $o->buyer_email, 'order' => $o->code, 'tickets' => $tickets->count(), 'pdf' => (bool) $pdf, 'forced' => $force];

        \Mail::html($mail['html'], function ($m) use ($o, $mail, $pdf) {
            $m->to($o->buyer_email, $o->buyer_name)->subject($mail['subject']);
            if ($pdf) {
                $m->attachData($pdf, 'entradas-' . strtolower($o->code) . '.pdf', ['mime' => 'application/pdf']);
            }
        });

        Order::whereKey($o->id)->update(['tickets_sent_at' => now(), 'tickets_send_count' => DB::raw('tickets_send_count + 1')]);

        return true;
    }
}
