<?php

use Aero\Sites\Models\Plan;
use October\Rain\Database\Updates\Migration;

/**
 * Deja «Workspaces (Oficina IA)» justo debajo de «Páginas» en el plan NEGOCIO
 * (la versión anterior lo había puesto primero). Idempotente; si el superadmin
 * ya lo movió a otro sitio distinto del primer lugar, no lo toca.
 */
return new class extends Migration
{
    public function up(): void
    {
        $plan = Plan::where('code', 'negocio')->first();

        if (!$plan) {
            return;
        }

        $features = array_values((array) $plan->features);
        $text = fn ($row) => trim((string) ($row['text'] ?? ''));
        $index = fn (string $label) => array_key_first(array_filter($features, fn ($row) => $text($row) === $label));

        $from = $index('Workspaces (Oficina IA)');
        $after = $index('Páginas');

        // Solo corrige el caso de la versión anterior: primero de la lista.
        if ($from !== 0 || $after === null) {
            return;
        }

        $item = array_shift($features);
        $at = array_key_first(array_filter($features, fn ($row) => $text($row) === 'Páginas')) + 1;
        array_splice($features, $at, 0, [$item]);

        $plan->features = $features;
        $plan->save();
    }

    public function down(): void
    {
    }
};
