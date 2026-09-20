<?php

use Aero\Notify\Models\Event;
use Aero\Notify\Models\Template;
use October\Rain\Database\Updates\Migration;

/**
 * shop.order.placed / paid: si el pedido lleva dirección de envío, el aviso la
 * incluye con el enlace al mapa cuando el cliente compartió su ubicación.
 */
return new class extends Migration
{
    public function up(): void
    {
        $events = Event::whereIn('code', ['shop.order.placed', 'shop.order.paid'])->pluck('id');

        foreach (Template::whereIn('event_id', $events)->where('tenant_id', Template::GLOBAL_TENANT)->get() as $tpl) {
            if (str_contains($tpl->body, 'location_url') || str_contains($tpl->body, 'shipping_address')) {
                continue;
            }

            $extra = "{% if shipping_address %} Envío: {{ shipping_address }}.{% endif %}{% if location_url %} Ubicación: {{ location_url }}{% endif %}";

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
