<?php

use October\Rain\Database\Updates\Migration;

/**
 * El plan PRO incluye Gimnasio, Colegio y Aviso de cookies, que ya traía
 * NEGOCIO. Idempotente; un plan que no restringe plugins (null = todos) no
 * se toca.
 */
return new class extends Migration
{
    protected array $plugins = ['Aero.Gym', 'Aero.School', 'Aero.Rgpd'];

    public function up(): void
    {
        $plan = \Aero\Sites\Models\Plan::where('code', 'pro')->first();
        if (!$plan || !is_array($plan->plugins)) {
            return;
        }

        $installed = array_filter($this->plugins, fn ($code) => class_exists(str_replace('.', '\\', $code) . '\\Plugin'));
        $missing = array_diff($installed, $plan->plugins);

        if ($missing) {
            $plan->plugins = array_values(array_merge($plan->plugins, $missing));
            $plan->save();
        }
    }

    public function down(): void
    {
        $plan = \Aero\Sites\Models\Plan::where('code', 'pro')->first();
        if ($plan && is_array($plan->plugins)) {
            $plan->plugins = array_values(array_diff($plan->plugins, $this->plugins));
            $plan->save();
        }
    }
};
