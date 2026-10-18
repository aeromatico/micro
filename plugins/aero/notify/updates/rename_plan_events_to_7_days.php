<?php

use Illuminate\Support\Facades\DB;
use October\Rain\Database\Updates\Migration;

/**
 * El Plan pasó de 30 a 7 días: actualiza los textos ya sembrados de los avisos
 * plan.* (eventos y plantillas) sin tocar nada que no mencione el plan antiguo.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['aero_notify_events' => ['name', 'description'], 'aero_notify_templates' => ['subject', 'body', 'body_html']] as $table => $columns) {
            foreach ($columns as $column) {
                DB::table($table)
                    ->where('code', 'like', 'plan.%')
                    ->where($column, 'like', '%Plan de 30 días%')
                    ->update([$column => DB::raw("REPLACE({$column}, 'Plan de 30 días', 'Plan de 7 días')")]);
            }
        }
    }

    public function down(): void
    {
    }
};
