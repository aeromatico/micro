<?php

use Aero\Notify\Models\Event;
use Aero\Notify\Models\Template;
use October\Rain\Database\Updates\Migration;

/**
 * Aero.Crm renderiza el texto del recordatorio con la plantilla propia de cada
 * regla/tenant y lo pasa como `mensaje` también al vencer; la plantilla global lo usa tal cual y
 * solo cae al texto por defecto si no llega.
 */
return new class extends Migration
{
    public function up(): void
    {
        $event = Event::where('code', 'crm.collection.overdue')->first();

        if (!$event) {
            return;
        }

        Template::where('event_id', $event->id)
            ->where('tenant_id', Template::GLOBAL_TENANT)
            ->where('channel', 'whatsapp')
            ->update(['body' => '{{ mensaje|default("Hola " ~ contacto ~ ", te recordamos tu pago de " ~ monto ~ " " ~ moneda ~ " (" ~ concepto ~ ") con vencimiento el " ~ vencimiento ~ ".") }}']);
    }

    public function down(): void
    {
    }
};
