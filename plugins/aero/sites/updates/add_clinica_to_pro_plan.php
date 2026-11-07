<?php

use October\Rain\Database\Updates\Migration;

/**
 * Aero.Clinica (Clínica, medicina humana) entra en el plan PRO. Idempotente; si el plan no
 * existe o no restringe plugins (null = todos), no hace nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!class_exists(\Aero\Clinica\Plugin::class)) {
            return;
        }

        $plan = \Aero\Sites\Models\Plan::where('name', 'PRO')->first();
        if (!$plan || !is_array($plan->plugins)) {
            return;
        }

        if (!in_array('Aero.Clinica', $plan->plugins, true)) {
            $plan->plugins = array_values(array_merge($plan->plugins, ['Aero.Clinica']));
            $plan->save();
        }
    }

    public function down(): void
    {
        $plan = \Aero\Sites\Models\Plan::where('name', 'PRO')->first();
        if ($plan && is_array($plan->plugins)) {
            $plan->plugins = array_values(array_diff($plan->plugins, ['Aero.Clinica']));
            $plan->save();
        }
    }
};
