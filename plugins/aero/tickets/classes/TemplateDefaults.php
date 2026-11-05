<?php namespace Aero\Tickets\Classes;

/** Plantillas de aviso de fábrica (las que edita el administrador parten de aquí). {{x}} se escapa; {{x_html}} entra tal cual (lo arma el sistema). */
class TemplateDefaults
{
    /** Las que no se pueden desactivar: sin ellas el comprador no recibe lo que pagó. */
    public const CRITICAL = ['ticket_delivery', 'mytickets_link'];

    public static function all(): array
    {
        $wrap = fn (string $inner) => '<div style="font-family:Arial,sans-serif;max-width:560px">' . $inner . '{{support_html}}</div>';

        return [
            'ticket_delivery' => [
                'name' => 'Entradas enviadas al comprador',
                'subject' => 'Tus entradas para {{event_name}}',
                'body' => $wrap('<h2>Tus entradas para {{event_name}}</h2><p>Hola {{buyer_name}},</p>{{payment_note_html}}<p><strong>{{when}}</strong><br>{{where}}</p><p>Abre cada entrada desde el celular y muestra el código QR en la puerta. Cada entrada se valida por separado:</p><ul>{{tickets_html}}</ul>{{pdf_note_html}}<p><a href="{{order_url}}">Ver mi orden</a></p><p style="color:#666;font-size:12px">No compartas estos enlaces: quien presente primero el QR ingresa.</p>'),
                'sample' => ['event_name' => 'Concierto de prueba', 'buyer_name' => 'Ana', 'when' => 'viernes 6 de noviembre, 20:00', 'where' => 'Sala Principal, La Paz', 'payment_note_html' => '<p>Confirmamos tu pago de <strong>Bs 100,00</strong>.</p>', 'tickets_html' => '<li>Ana — código ABCD234567</li>', 'pdf_note_html' => '<p>También las adjuntamos en PDF.</p>', 'order_url' => 'https://ejemplo.bo/orden/XXXX', 'support_html' => ''],
            ],
            'mytickets_link' => [
                'name' => 'Enlace de «Mis entradas»',
                'subject' => 'Tus entradas',
                'body' => $wrap('<p>Hola,</p><p>Usa este enlace para ver tus entradas (vale 60 minutos):</p><p><a href="{{link_url}}">Ver mis entradas</a></p><p style="color:#666;font-size:12px">Si no lo pediste, ignora este correo.</p>'),
                'sample' => ['link_url' => 'https://ejemplo.bo/mis-entradas/ver?x=1', 'support_html' => ''],
            ],
            'payment_rejected' => [
                'name' => 'Pago rechazado',
                'subject' => 'No pudimos verificar tu pago: {{event_name}}',
                'body' => $wrap('<p>Hola {{buyer_name}},</p><p>No pudimos verificar tu pago para «{{event_name}}».</p><p><strong>Motivo:</strong> {{reason}}</p>{{retry_html}}<p><a href="{{order_url}}">Ver mi orden</a></p>'),
                'sample' => ['buyer_name' => 'Ana', 'event_name' => 'Concierto de prueba', 'reason' => 'No aparece en la cuenta', 'retry_html' => '<p>Tus entradas siguen reservadas un tiempo para que lo intentes de nuevo.</p>', 'order_url' => 'https://ejemplo.bo/orden/XXXX', 'support_html' => ''],
            ],
            'refund_pending' => [
                'name' => 'Pago a devolver',
                'subject' => 'Tu pago será devuelto: {{event_name}}',
                'body' => $wrap('<p>Hola {{buyer_name}},</p><p>Recibimos tu pago de <strong>{{amount}}</strong> pero no pudimos emitir tus entradas ({{reason}}).</p><p>El organizador te devolverá el dinero y te contactará.</p><p><a href="{{order_url}}">Ver mi orden</a></p>'),
                'sample' => ['buyer_name' => 'Ana', 'event_name' => 'Concierto de prueba', 'amount' => 'Bs 100,00', 'reason' => 'ya no había lugares', 'order_url' => 'https://ejemplo.bo/orden/XXXX', 'support_html' => ''],
            ],
            'organizer_approved' => [
                'name' => 'Organizador aprobado',
                'subject' => 'Ya puedes publicar eventos',
                'body' => $wrap('<p>Hola {{contact_name}},</p><p>Aprobamos tu cuenta de organizador. Ya puedes publicar eventos y vender entradas.</p>{{note_html}}'),
                'sample' => ['contact_name' => 'Rita', 'note_html' => '', 'support_html' => ''],
            ],
            'organizer_rejected' => [
                'name' => 'Organizador rechazado',
                'subject' => 'Sobre tu solicitud de organizador',
                'body' => $wrap('<p>Hola {{contact_name}},</p><p>Por ahora no pudimos aprobar tu solicitud de organizador.</p><p><strong>Motivo:</strong> {{note}}</p><p>Puedes corregir tus datos y volver a solicitarla.</p>'),
                'sample' => ['contact_name' => 'Rita', 'note' => 'Falta documento', 'support_html' => ''],
            ],
            'organizer_suspended' => [
                'name' => 'Organizador suspendido',
                'subject' => 'Tu cuenta de organizador fue suspendida',
                'body' => $wrap('<p>Hola {{contact_name}},</p><p>Suspendimos temporalmente tu cuenta de organizador: no puedes publicar ni vender entradas hasta nuevo aviso.</p><p><strong>Motivo:</strong> {{note}}</p>'),
                'sample' => ['contact_name' => 'Rita', 'note' => 'Reclamos de compradores', 'support_html' => ''],
            ],
            'event_rejected' => [
                'name' => 'Evento rechazado en moderación',
                'subject' => 'Tu evento «{{event_name}}» necesita cambios',
                'body' => $wrap('<p>Hola {{contact_name}},</p><p>Revisamos «{{event_name}}» y no pudimos publicarlo todavía.</p><p><strong>Motivo:</strong> {{note}}</p><p>Corrígelo y vuelve a publicarlo.</p>'),
                'sample' => ['contact_name' => 'Rita', 'event_name' => 'Concierto de prueba', 'note' => 'La descripción no es clara', 'support_html' => ''],
            ],
            'event_suspended' => [
                'name' => 'Evento suspendido',
                'subject' => 'Suspendimos tu evento «{{event_name}}»',
                'body' => $wrap('<p>Hola {{contact_name}},</p><p>Suspendimos «{{event_name}}»: no está visible, no vende entradas y no permite el ingreso hasta que se resuelva.</p><p><strong>Motivo:</strong> {{note}}</p>'),
                'sample' => ['contact_name' => 'Rita', 'event_name' => 'Concierto de prueba', 'note' => 'Reporte de fraude', 'support_html' => ''],
            ],
            'event_cancelled_platform' => [
                'name' => 'Evento cancelado por la plataforma',
                'subject' => 'Cancelamos tu evento «{{event_name}}»',
                'body' => $wrap('<p>Hola {{contact_name}},</p><p>Cancelamos «{{event_name}}». Las órdenes pendientes se anularon y los pagos recibidos quedaron marcados para devolución.</p><p><strong>Motivo:</strong> {{note}}</p>'),
                'sample' => ['contact_name' => 'Rita', 'event_name' => 'Concierto de prueba', 'note' => 'Incumplimiento de las condiciones', 'support_html' => ''],
            ],
        ];
    }
}
