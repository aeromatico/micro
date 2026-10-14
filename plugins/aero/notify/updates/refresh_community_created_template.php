<?php

use Aero\Notify\Models\Event;
use Aero\Notify\Models\Template;
use October\Rain\Database\Updates\Migration;

/**
 * El compromiso de Comunidad pasó de días a minutos: la plantilla global ya
 * sembrada decía «Respuesta antes del {{ due_at }}». Solo toca la global
 * (tenant_id 0) y únicamente si conserva el texto original.
 */
return new class extends Migration
{
    public function up(): void
    {
        $event = Event::where('code', 'community.post.created')->first();
        if (!$event) {
            return;
        }

        Template::where('event_id', $event->id)->where('tenant_id', Template::GLOBAL_TENANT)->get()->each(function ($t) {
            if (str_contains((string) $t->body, 'Respuesta antes del {{ due_at }}.')) {
                $t->body = str_replace('Respuesta antes del {{ due_at }}.', 'Compromiso: responder antes de {{ due_at }}.', $t->body);
                $t->save();
            }
        });
    }

    public function down(): void
    {
    }
};
