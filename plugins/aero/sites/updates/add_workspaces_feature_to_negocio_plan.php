<?php

use Aero\Sites\Models\Plan;
use October\Rain\Database\Updates\Migration;

/**
 * Destaca «Workspaces (Oficina IA)» en el plan NEGOCIO (landing → #precios).
 * Idempotente: si el ítem ya existe (por su texto) no lo repite, y respeta lo
 * que el superadmin haya editado después en Sites → Planes.
 */
return new class extends Migration
{
    public const TEXT = 'Workspaces (Oficina IA)';

    public function up(): void
    {
        $plan = Plan::where('code', 'negocio')->first();

        if (!$plan) {
            return;
        }

        $features = array_values((array) $plan->features);

        foreach ($features as $row) {
            if (trim((string) ($row['text'] ?? '')) === static::TEXT) {
                return;
            }
        }

        $item = [
            'text'        => static::TEXT,
            'featured'    => '1',
            'description' => 'Crea una oficina virtual eficiente contratando agentes especializados IA e incluso humano expertos',
            'tooltip'     => '',
        ];

        // Justo debajo de «Páginas»; si ya no existe, al final de las destacadas.
        $at = count($features);

        foreach ($features as $i => $row) {
            if (trim((string) ($row['text'] ?? '')) === 'Páginas') {
                $at = $i + 1;
                break;
            }
        }

        array_splice($features, $at, 0, [$item]);

        $plan->features = $features;
        $plan->save();
    }

    public function down(): void
    {
        $plan = Plan::where('code', 'negocio')->first();

        if ($plan) {
            $plan->features = array_values(array_filter((array) $plan->features, fn ($row) => trim((string) ($row['text'] ?? '')) !== static::TEXT));
            $plan->save();
        }
    }
};
