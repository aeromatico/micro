<?php

use October\Rain\Database\Updates\Migration;

/**
 * Finanzas (contabilidad por negocio) entra en el plan PRO. Idempotente; si el
 * plan no existe o no restringe plugins (null = todos), no hace nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!class_exists(\Aero\Finance\Plugin::class)) {
            return;
        }

        $plan = \Aero\Sites\Models\Plan::where('name', 'PRO')->first();
        if (!$plan || !is_array($plan->plugins)) {
            return;
        }

        if (!in_array('Aero.Finance', $plan->plugins, true)) {
            $plan->plugins = array_values(array_merge($plan->plugins, ['Aero.Finance']));
            $plan->save();
        }
    }

    public function down(): void
    {
        $plan = \Aero\Sites\Models\Plan::where('name', 'PRO')->first();
        if ($plan && is_array($plan->plugins)) {
            $plan->plugins = array_values(array_diff($plan->plugins, ['Aero.Finance']));
            $plan->save();
        }
    }
};
