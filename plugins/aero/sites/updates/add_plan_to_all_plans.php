<?php

use October\Rain\Database\Updates\Migration;

/**
 * El Plan de 7 días (Aero.Plan) es acompañamiento de bienvenida: entra en
 * todos los planes que restringen plugins (null = todos, no hace falta).
 * Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!class_exists(\Aero\Plan\Plugin::class)) {
            return;
        }

        foreach (\Aero\Sites\Models\Plan::all() as $plan) {
            if (is_array($plan->plugins) && !in_array('Aero.Plan', $plan->plugins, true)) {
                $plan->plugins = array_values(array_merge($plan->plugins, ['Aero.Plan']));
                $plan->save();
            }
        }
    }

    public function down(): void
    {
        foreach (\Aero\Sites\Models\Plan::all() as $plan) {
            if (is_array($plan->plugins)) {
                $plan->plugins = array_values(array_diff($plan->plugins, ['Aero.Plan']));
                $plan->save();
            }
        }
    }
};
