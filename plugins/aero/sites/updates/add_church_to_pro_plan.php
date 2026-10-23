<?php

use October\Rain\Database\Updates\Migration;

/**
 * Iglesia (miembros, grupos y eventos) entra en el plan PRO. Idempotente; si el
 * plan no existe o no restringe plugins (null = todos), no hace nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!class_exists(\Aero\Church\Plugin::class)) {
            return;
        }

        $plan = \Aero\Sites\Models\Plan::where('name', 'PRO')->first();
        if (!$plan || !is_array($plan->plugins)) {
            return;
        }

        if (!in_array('Aero.Church', $plan->plugins, true)) {
            $plan->plugins = array_values(array_merge($plan->plugins, ['Aero.Church']));
            $plan->save();
        }
    }

    public function down(): void
    {
        $plan = \Aero\Sites\Models\Plan::where('name', 'PRO')->first();
        if ($plan && is_array($plan->plugins)) {
            $plan->plugins = array_values(array_diff($plan->plugins, ['Aero.Church']));
            $plan->save();
        }
    }
};
