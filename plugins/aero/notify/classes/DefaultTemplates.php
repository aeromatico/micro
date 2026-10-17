<?php namespace Aero\Notify\Classes;

use Aero\Notify\Models\Event;
use Aero\Notify\Models\Rule;
use Aero\Notify\Models\Template;

/**
 * Título + texto por defecto de cada evento del catálogo. Un solo texto sirve
 * para todos los canales (email lo envuelve en HTML). Las plantillas ya
 * existentes nunca se tocan: solo se crean las que faltan para cada regla
 * global (evento x canal).
 */
class DefaultTemplates
{
    public const TEXTS = [
        'support.ticket.received' => ['Recibimos tu ticket {{ ticket_number }}', 'Hola {{ requester_name }}, recibimos tu ticket {{ ticket_number }} «{{ subject }}» y te responderemos a la brevedad. Puedes seguirlo aquí: {{ url }}'],
        'support.ticket.opened' => ['Nuevo ticket {{ ticket_number }}', '{{ requester_name }}{% if requester_email %} ({{ requester_email }}){% endif %} abrió el ticket {{ ticket_number }} «{{ subject }}»{% if department %} en {{ department }}{% endif %}: {{ message }} {{ url }}'],
        'support.ticket.customer_replied' => ['Respuesta en el ticket {{ ticket_number }}', '{{ requester_name }} respondió en «{{ subject }}» ({{ ticket_number }}): {{ message }} {{ url }}'],
        'support.ticket.agent_replied' => ['Respondimos tu ticket {{ ticket_number }}', 'Hola {{ requester_name }}, respondimos tu ticket «{{ subject }}» ({{ ticket_number }}): {{ message }} Míralo y responde aquí: {{ url }}'],
        'support.ticket.platform_opened' => ['Ticket {{ ticket_number }} de {{ tenant_name }}', '{{ requester_name }}{% if tenant_name %} ({{ tenant_name }}){% endif %} abrió el ticket {{ ticket_number }} «{{ subject }}»: {{ message }} {{ url }}'],
        'support.ticket.platform_customer_replied' => ['Respuesta en el ticket {{ ticket_number }}', '{{ requester_name }}{% if tenant_name %} ({{ tenant_name }}){% endif %} respondió en «{{ subject }}» ({{ ticket_number }}): {{ message }} {{ url }}'],
        'plan.step.submitted' => ['Paso {{ step_number }} del plan de {{ tenant_name }}', '{{ tenant_name }} envió el paso {{ step_number }} «{{ step_name }}» de su Plan de 30 días (día {{ day }}). Revísalo: {{ url }}'],
        'plan.message.from_client' => ['Mensaje de {{ tenant_name }} en su plan', '{{ tenant_name }} escribió en el paso {{ step_number }} «{{ step_name }}»: {{ message }} {{ url }}'],
        'plan.paused' => ['{{ tenant_name }} dejó su plan', '{{ tenant_name }} decidió seguir por su cuenta (día {{ day }}). El plan queda en pausa y puede retomarlo cuando quiera.'],
        'plan.resumed' => ['{{ tenant_name }} retomó su plan', '{{ tenant_name }} retomó su Plan de 30 días en el paso {{ step_number }} «{{ step_name }}». {{ url }}'],
        'plan.step.approved' => ['Aprobamos el paso {{ step_number }} de tu plan', 'Aprobamos el paso {{ step_number }} «{{ step_name }}».{% if next_step %} Ya puedes completar «{{ next_step }}».{% endif %} {{ url }}'],
        'plan.changes.requested' => ['Ajustes en el paso {{ step_number }} de tu plan', 'Revisamos el paso {{ step_number }} «{{ step_name }}» y necesitamos algunos ajustes: {{ message }} {{ url }}'],
        'plan.message.from_team' => ['Nuevo mensaje en tu plan', 'Te escribimos en el paso {{ step_number }} «{{ step_name }}»: {{ message }} {{ url }}'],
        'plan.completed' => ['¡Completaste tu Plan de 30 días!', '¡Felicitaciones! Aprobamos el último paso de tu plan en {{ day }} días. Tu sistema está en marcha y seguimos a tu lado.'],
        'community.comment.added' => ['Nuevo comentario', '{{ author_name }} comentó en «{{ title }}»: {{ comment }} {{ url }}'],
        'community.post.created' => ['{{ type_label }} nueva', '{{ author_name }}{% if tenant_name %} ({{ tenant_name }}){% endif %}: «{{ title }}». Compromiso: responder antes de {{ due_at }}. {{ url }}'],
        'community.post.overdue' => ['Compromiso vencido', '«{{ title }}» ({{ type_label }}) pasó su plazo sin respuesta. {{ url }}'],
        'community.post.responded' => ['Respondimos tu reporte', 'Sobre «{{ title }}»: {{ status }}.{% if response %} {{ response }}{% endif %} {{ url }}'],
        'community.post.shipped' => ['¡Ya está disponible!', 'La sugerencia «{{ title }}», por la que votaste, ya fue implementada. Gracias por ayudarnos a mejorar.'],
        'credits.balance.depleted' => ['Saldo agotado', 'Tu saldo de créditos {{ credit_type }} se agotó ({{ balance }}). Recarga para seguir usando el servicio.'],
        'credits.balance.low' => ['Saldo bajo', 'Tu saldo de créditos {{ credit_type }} es {{ balance }}. Considera recargar pronto.'],
        'crm.collection.overdue' => ['Pago vencido', 'Hola {{ contacto }}, tu pago de {{ monto }} {{ moneda }} ({{ concepto }}) venció el {{ vencimiento }}. Por favor regulariza tu pago.'],
        'crm.collection.reminder' => ['Recordatorio de pago', 'Hola {{ contacto }}, te recordamos tu pago de {{ monto }} {{ moneda }} ({{ concepto }}) con vencimiento el {{ vencimiento }}.'],
        'crm.deal.lost' => ['Negocio perdido', 'Se perdió el negocio «{{ deal_name }}». {{ reason }}'],
        'crm.deal.stage_changed' => ['Negocio actualizado', '«{{ deal_name }}» pasó a la etapa {{ to_stage }}.'],
        'crm.deal.won' => ['¡Negocio ganado!', 'Se ganó el negocio «{{ deal_name }}» {{ amount }}.'],
        'crm.lead.created' => ['Nuevo lead', 'Nuevo lead: {{ lead_name }} {{ source }}.'],
        'crm.task.due' => ['Tarea por vencer', 'La tarea «{{ subject }}» vence el {{ due_at }}.'],
        'hello.account.disconnected' => ['Cuenta desconectada', 'La cuenta {{ account_label }} ({{ platform }}) se desconectó. Reconéctala para seguir recibiendo mensajes.'],
        'hello.call.missed' => ['Llamada perdida', 'Llamada perdida de {{ contact_name ?: from_number }}.'],
        'hello.campaign.finished' => ['Campaña finalizada', 'La campaña «{{ campaign_name }}» terminó: {{ sent_count }} enviados, {{ failed_count }} fallidos.'],
        'hello.message.received' => ['Mensaje de {{ contact_name }}', '{{ preview }}'],
        'medical.vaccine.due' => ['Vacuna próxima', 'Hola {{ customer_name }}, {{ patient_name }} tiene {{ vaccine_text }} programada para el {{ due_date }}.{% if business_name %} Escríbenos a {{ business_name }} para agendar.{% endif %}'],
        'medical.deworming.due' => ['Desparasitación próxima', 'Hola {{ customer_name }}, {{ patient_name }} tiene una desparasitación programada para el {{ due_date }}.{% if business_name %} Escríbenos a {{ business_name }} para agendar.{% endif %}'],
        'notify.delivery.failed_burst' => ['Fallos de entrega', '{{ failed_count }} entregas fallaron por {{ channel }} en {{ window }}.'],
        'pay.billing.run_completed' => ['Facturación completada', 'Se generaron {{ charges_count }} cargos ({{ total_amount }}). Errores: {{ errors_count ?: 0 }}.'],
        'pay.charge.created' => ['Nuevo cargo', 'Se generó un cargo de {{ amount }} {{ currency }} ({{ period_start }} – {{ period_end }}). Vence: {{ due_date }}.'],
        'pay.charge.overdue' => ['Cargo vencido', 'El cargo de {{ amount }} {{ currency }} de {{ tenant_name }} lleva {{ days_late }} días vencido.'],
        'pay.payment.failed' => ['Pago fallido', 'Falló un pago de {{ amount }} {{ currency }}. {{ reason }}'],
        'pay.payment.received' => ['Pago recibido', 'Recibiste {{ amount }} {{ currency }} de {{ payer_name }}. {{ reference }}'],
        'pay.qr.expired' => ['QR vencido', 'El QR de {{ amount }} {{ currency }} venció sin pagarse. {{ reference }}'],
        'pay.qr.generated' => ['Tu QR de pago', 'Tienes un cobro de {{ amount }} {{ currency }}. {{ description }}'],
        'pay.quota.exceeded' => ['Cuota excedida', 'Superaste la cuota «{{ quota }}» de tu plan {{ plan_name }}.'],
        'pay.subscription.cancelled' => ['Suscripción cancelada', 'La suscripción al plan {{ plan_name }} de {{ tenant_name }} fue cancelada. {{ reason }}'],
        'pay.subscription.renewed' => ['Suscripción renovada', 'Tu plan {{ plan_name }} se renovó.'],
        'office.booking.created' => ['Nueva reserva {{ code }}', '{{ customer_name }} reservó {{ service_name }} con {{ worker_name }} para el {{ starts_at }}{% if branch_name %} ({{ branch_name }}){% endif %}. Revísala en el panel.'],
        'office.booking.received' => ['Recibimos tu solicitud', 'Hola {{ customer_name }}, recibimos tu solicitud de {{ service_name }} para el {{ starts_at }}. Te confirmaremos pronto. Código {{ code }}.{% if url %} Gestiona tu cita: {{ url }}{% endif %}'],
        'office.booking.confirmed' => ['Cita confirmada', 'Hola {{ customer_name }}, tu cita de {{ service_name }} con {{ worker_name }} quedó confirmada para el {{ starts_at }}{% if branch_name %} en {{ branch_name }}{% if branch_address %} ({{ branch_address }}){% endif %}{% endif %}. Código {{ code }}.{% if url %} Gestiona tu cita: {{ url }}{% endif %}'],
        'office.booking.rejected' => ['Solicitud no aceptada', 'Hola {{ customer_name }}, lamentablemente no pudimos aceptar tu solicitud de {{ service_name }} para el {{ starts_at }}.{% if reason %} Motivo: {{ reason }}.{% endif %} Puedes elegir otro horario.'],
        'office.booking.cancelled' => ['Cita cancelada', 'Hola {{ customer_name }}, tu cita de {{ service_name }} del {{ starts_at }} fue cancelada.{% if reason %} Motivo: {{ reason }}.{% endif %} Disculpa las molestias; puedes reservar otro horario.'],
        'office.booking.cancelled_by_customer' => ['Reserva {{ code }} cancelada', '{{ customer_name }} canceló {{ service_name }} del {{ starts_at }}. El horario quedó libre.'],
        'office.booking.rescheduled' => ['Cita reprogramada', 'Hola {{ customer_name }}, tu cita de {{ service_name }} se movió al {{ starts_at }} con {{ worker_name }}{% if branch_name %} en {{ branch_name }}{% endif %}. Código {{ code }}.{% if url %} Gestiona tu cita: {{ url }}{% endif %}'],
        'office.booking.rescheduled_by_customer' => ['Reserva {{ code }} reprogramada', '{{ customer_name }} movió {{ service_name }} al {{ starts_at }} con {{ worker_name }}.'],
        'office.booking.reminder' => ['Recordatorio de tu cita', 'Hola {{ customer_name }}, te recordamos tu cita de {{ service_name }} con {{ worker_name }} el {{ starts_at }}{% if branch_name %} en {{ branch_name }}{% if branch_address %} ({{ branch_address }}){% endif %}{% endif %}.{% if url %} Si no puedes asistir, cancélala aquí: {{ url }}{% endif %}'],
        'shop.cart.abandoned' => ['Dejaste algo en tu carrito', 'Hola {{ customer_name }}, tu carrito sigue esperándote. {{ cart_url }}'],
        'shop.customer.registered' => ['Bienvenido', 'Hola {{ customer_name }}, tu cuenta en {{ tenant_name }} está lista.'],
        'shop.order.cancelled' => ['Pedido {{ order_number }} cancelado', 'El pedido {{ order_number }} de {{ customer_name }} ({{ total }} {{ currency }}) fue cancelado. {{ reason }}'],
        'shop.order.paid' => ['Pedido {{ order_number }} pagado', 'Se registró el pago del pedido {{ order_number }} de {{ customer_name }}: {{ total }} {{ currency }}.'],
        'shop.order.placed' => ['Nuevo pedido {{ order_number }}', 'Pedido {{ order_number }} de {{ customer_name }} por {{ total }} {{ currency }}.'],
        'shop.order.ready' => ['Pedido {{ order_number }} listo', 'Hola {{ customer_name }}, tu pedido {{ order_number }} está listo{% if table_label %} (mesa {{ table_label }}){% elseif order_type == "Recoger" %}: pásalo a recoger{% endif %}.'],
        'shop.order.shipped' => ['Pedido {{ order_number }} enviado', 'Hola {{ customer_name }}, tu pedido {{ order_number }} va en camino. {{ tracking_code }}'],
        'shop.stock.low' => ['Stock bajo', '«{{ product_name }}» tiene solo {{ stock }} unidades.'],
        'sites.domain.expiring' => ['Dominio por vencer', 'El dominio {{ domain }} vence el {{ expires_at }} (faltan {{ days_left }} días).'],
        'sites.domain.verified' => ['Dominio verificado', 'El dominio {{ domain }} quedó verificado y activo.'],
        'system.security.login_new_device' => ['Nuevo inicio de sesión', 'Hola {{ user_name }}, se inició sesión desde {{ ip }} ({{ user_agent }}). Si no fuiste tú, cambia tu contraseña.'],
        'system.user.password_reset' => ['Restablece tu contraseña', 'Hola {{ user_name }}, restablece tu contraseña aquí: {{ reset_url }}'],
        'system.user.welcome' => ['Bienvenido', 'Hola {{ user_name }}, tu cuenta está lista. {{ login_url }}'],
    ];

    /** @return int plantillas creadas */
    public static function seedMissing(): int
    {
        $created = 0;

        foreach (Rule::global()->with('event')->get() as $rule) {
            $event = $rule->event;
            $text  = static::TEXTS[$event?->code] ?? null;

            if (!$text) {
                continue;
            }

            $exists = Template::where('event_id', $event->id)
                ->where('channel', $rule->channel)
                ->where('tenant_id', Template::GLOBAL_TENANT)
                ->where('locale', 'es')
                ->exists();

            if ($exists) {
                continue;
            }

            [$title, $body] = $text;

            $template = new Template();
            $template->event_id  = $event->id;
            $template->tenant_id = Template::GLOBAL_TENANT;
            $template->channel   = $rule->channel;
            $template->locale    = 'es';
            $template->format    = 'twig';
            $template->subject   = $template->hasSubject() ? $title : null;
            $template->body      = $rule->channel === 'email' ? '<p>' . $body . '</p>' : $body;
            $template->save();

            $created++;
        }

        return $created;
    }
}
