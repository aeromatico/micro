<?php

use Aero\Notify\Models\Event;
use Aero\Notify\Models\Rule;
use October\Rain\Database\Updates\Migration;

/** Un aviso por conversación por minuto: sin esto cada mensaje entrante dispara push. */
return new class extends Migration
{
    public function up(): void
    {
        $event = Event::where('code', 'hello.message.received')->first();

        if ($event) {
            Rule::where('event_id', $event->id)->where('dedup_window_min', 0)->update(['dedup_window_min' => 1]);
        }
    }

    public function down(): void
    {
    }
};
