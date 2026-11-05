<?php namespace Aero\Tickets\Classes;

use Aero\Tickets\Models\Event;
use Aero\Tickets\Models\Order;
use Aero\Tickets\Models\Ticket;
use Aero\Tickets\Models\TicketType;

/** Presentación de una entrada: datos, QR (SVG) y PDF. El QR solo lleva el payload firmado (código), nunca datos personales. */
class TicketRenderer
{
    public static function qrSvg(Ticket $t, int $size = 240): string
    {
        $svg = (new \BaconQrCode\Writer(new \BaconQrCode\Renderer\ImageRenderer(
            new \BaconQrCode\Renderer\RendererStyle\RendererStyle($size, 1),
            new \BaconQrCode\Renderer\Image\SvgImageBackEnd()
        )))->writeString($t->qr_payload);

        return trim(preg_replace('/^<\?xml[^>]*\?>\s*/', '', $svg));
    }

    public static function qrDataUri(Ticket $t, int $size = 240): string
    {
        return 'data:image/svg+xml;base64,' . base64_encode(self::qrSvg($t, $size));
    }

    /** Todo lo que se muestra de una entrada (web, PDF y correo comparten estos datos). */
    public static function details(Ticket $t): array
    {
        $e = Event::withTrashed()->find($t->event_id);
        $type = $t->ticket_type_id ? TicketType::find($t->ticket_type_id) : null;
        $session = $t->session_id ? \Aero\Tickets\Models\Session::find($t->session_id) : null;
        $order = $t->order_id ? Order::find($t->order_id) : null;
        $total = $order ? (int) Ticket::where('order_id', $order->id)->whereNotIn('status', ['cancelled', 'expired'])->count() : null;

        $notes = [];
        if ($e->min_age) {
            $notes[] = 'Edad mínima: ' . $e->min_age . ' años.';
        }
        if ($e->requirements) {
            $notes[] = trim($e->requirements);
        }
        $notes[] = 'Presenta este código QR en la puerta (desde el celular o impreso). Cada entrada sirve para una sola persona y un solo ingreso.';
        $notes[] = 'No compartas tu QR ni tu enlace: quien lo use primero ingresa.';

        return [
            'ticket' => $t, 'event' => $e, 'type' => $type, 'session' => $session, 'order' => $order,
            'holder' => $t->holder_name,
            'origin' => $order ? 'Orden ' . $order->code : null,
            'serial' => $t->seq && $total ? "Entrada {$t->seq} de " . max($total, $t->seq) : null,
            'when' => $e->starts_at?->translatedFormat('l d \d\e F \d\e Y, H:i') . ($e->ends_at ? ' a ' . $e->ends_at->format('H:i') : ''),
            'where' => trim(implode(', ', array_filter([$e->venue_name, $e->address, $e->zone, $e->city]))),
            'reference' => $e->address_reference,
            'contact' => trim(implode(' · ', array_filter([$e->contact_name, $e->contact_phone, $e->contact_email]))),
            'notes' => $notes,
            'showQr' => in_array($t->status, ['valid', 'used'], true),
        ];
    }

    public static function pdfAvailable(): bool
    {
        return class_exists(\Dompdf\Dompdf::class);
    }

    /** @param Ticket[] $tickets una página por entrada */
    public static function pdfHtml(iterable $tickets): string
    {
        $css = 'body{font-family:DejaVu Sans,sans-serif;color:#111;font-size:12px}'
            . '.page{page-break-after:always;border:2px solid #222;border-radius:12px;padding:18px}.page:last-child{page-break-after:auto}'
            . 'h1{font-size:20px;margin:0 0 4px}.mut{color:#555}.qr{text-align:center;margin:14px 0}.code{font:bold 18px monospace;letter-spacing:3px;text-align:center}'
            . 'table{width:100%;border-collapse:collapse}td{padding:4px 0;vertical-align:top}td.k{width:34%;color:#555}'
            . '.state{display:inline-block;padding:3px 10px;border-radius:99px;background:#eee;font-weight:bold}.note{font-size:10.5px;color:#444;margin:3px 0}'
            . '.void{color:#b42318;font-size:22px;font-weight:bold;text-align:center;margin:30px 0}';
        $out = '<!doctype html><html lang="es"><head><meta charset="utf-8"><style>' . $css . '</style></head><body>';
        foreach ($tickets as $t) {
            $d = self::details($t);
            $e = $d['event'];
            $out .= '<div class="page"><h1>' . e($e->name) . '</h1><div class="mut">' . e($d['when']) . '</div>';
            $out .= '<p><span class="state">' . e($t->state_label) . '</span> ' . e($d['serial'] ?? '') . '</p>';
            if ($d['showQr']) {
                $out .= '<div class="qr"><img src="' . self::qrDataUri($t, 220) . '" width="220" height="220"></div><div class="code">' . e($t->code) . '</div>';
            } else {
                $out .= '<div class="void">ENTRADA NO VÁLIDA PARA INGRESAR</div>';
            }
            $out .= '<table>'
                . '<tr><td class="k">Asistente</td><td><strong>' . e($d['holder']) . '</strong></td></tr>'
                . '<tr><td class="k">Entrada</td><td>' . e($d['type']?->name ?? 'General') . ($d['session'] ? ' · ' . e($d['session']->name) : '') . '</td></tr>'
                . '<tr><td class="k">Lugar</td><td>' . e($d['where']) . ($d['reference'] ? '<br><span class="mut">' . e($d['reference']) . '</span>' : '') . '</td></tr>'
                . ($d['origin'] ? '<tr><td class="k">Origen</td><td>' . e($d['origin']) . '</td></tr>' : '')
                . ($d['contact'] ? '<tr><td class="k">Consultas</td><td>' . e($d['contact']) . '</td></tr>' : '')
                . '</table><div style="margin-top:10px">';
            foreach ($d['notes'] as $n) {
                $out .= '<div class="note">• ' . e($n) . '</div>';
            }
            $out .= '</div></div>';
        }

        return $out . '</body></html>';
    }

    /** PDF con una página por entrada; null si no hay dompdf. */
    public static function pdf(iterable $tickets): ?string
    {
        if (!self::pdfAvailable()) {
            return null;
        }
        $o = new \Dompdf\Options();
        $o->set('isRemoteEnabled', false);
        $o->set('defaultFont', 'DejaVu Sans');
        $o->set('tempDir', storage_path('temp'));
        $o->set('fontCache', storage_path('temp'));

        $pdf = new \Dompdf\Dompdf($o);
        $pdf->loadHtml(self::pdfHtml($tickets), 'UTF-8');
        $pdf->setPaper('a5', 'portrait');
        $pdf->render();

        return $pdf->output();
    }

    public static function filename(Ticket $t): string
    {
        return 'entrada-' . strtolower($t->code) . '.pdf';
    }
}
