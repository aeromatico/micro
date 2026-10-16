<?php

use Aero\Sites\Models\Plan;
use October\Rain\Database\Updates\Migration;

/**
 * Los créditos de regalo de todos los planes pasan al modo «Una sola vez»
 * (cuota inicial sin vencimiento ni repetición en renovaciones). No otorga
 * nada: los tenants actuales conservan lo ya recibido y las renovaciones
 * dejan de sumar. Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (Plan::all() as $plan) {
            $rows = array_map(fn ($r) => array_merge((array) $r, ['mode' => 'once']), (array) $plan->credits);

            if ($rows && $rows !== (array) $plan->credits) {
                \DB::table($plan->getTable())->where('id', $plan->id)->update(['credits' => json_encode($rows)]);
            }
        }
    }

    public function down(): void
    {
    }
};
