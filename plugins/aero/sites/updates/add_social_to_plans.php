<?php

use October\Rain\Database\Updates\Migration;

/**
 * Aero.Social (Redes sociales) entra en los planes NEGOCIO y PRO. Idempotente;
 * un plan que no restringe plugins (null = todos) no se toca.
 */
return new class extends Migration
{
    protected array $plans = ['negocio', 'pro'];

    public function up(): void
    {
        if (!class_exists(\Aero\Social\Plugin::class)) {
            return;
        }

        foreach (\Aero\Sites\Models\Plan::whereIn('code', $this->plans)->get() as $plan) {
            if (is_array($plan->plugins) && !in_array('Aero.Social', $plan->plugins, true)) {
                $plan->plugins = array_values(array_merge($plan->plugins, ['Aero.Social']));
                $plan->save();
            }
        }
    }

    public function down(): void
    {
        foreach (\Aero\Sites\Models\Plan::whereIn('code', $this->plans)->get() as $plan) {
            if (is_array($plan->plugins)) {
                $plan->plugins = array_values(array_diff($plan->plugins, ['Aero.Social']));
                $plan->save();
            }
        }
    }
};
