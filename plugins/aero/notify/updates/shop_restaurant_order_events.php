<?php

use Aero\Notify\Classes\DefaultTemplates;
use Aero\Notify\Classes\EventSeeder;
use Aero\Notify\Models\Event;
use Aero\Notify\Models\Template;
use October\Rain\Database\Updates\Migration;

/**
 * Restaurante: evento nuevo shop.order.ready (pedido listo) y el aviso de
 * pedido nuevo (placed) suma tipo de pedido, mesa, hora programada y notas.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new EventSeeder)->run();
        DefaultTemplates::seedMissing();

        $eventId = Event::where('code', 'shop.order.placed')->value('id');
        foreach (Template::where('event_id', $eventId)->where('tenant_id', Template::GLOBAL_TENANT)->get() as $tpl) {
            if (str_contains($tpl->body, 'order_type')) {
                continue;
            }

            $extra = "{% if order_type %} Tipo: {{ order_type }}{% if table_label %}, mesa {{ table_label }}{% endif %}.{% endif %}"
                . "{% if scheduled_for %} Programado: {{ scheduled_for }}.{% endif %}"
                . "{% if items %} Detalle: {{ items }}.{% endif %}"
                . "{% if customer_notes %} Nota: {{ customer_notes }}{% endif %}";

            $tpl->body = $tpl->channel === 'email'
                ? preg_replace('#</p>\s*$#', $extra . '</p>', $tpl->body, 1)
                : rtrim($tpl->body) . $extra;
            $tpl->save();
        }
    }

    public function down(): void
    {
    }
};
