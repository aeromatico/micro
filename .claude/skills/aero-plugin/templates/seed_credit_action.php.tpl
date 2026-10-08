<?php

use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Updates\Seeder;

/**
 * Acción facturable de {Plugin}. Idempotente; no pisa el costo que el
 * superadmin ya haya ajustado. Sin Aero.Credits instalado no hace nada.
 */
return new class extends Seeder
{
    public function run(): void
    {
        // La clase puede existir sin que Credits haya migrado todavía (instalación
        // nueva, o tests): comprobar también las tablas.
        if (!class_exists(\Aero\Credits\Models\CreditAction::class)
            || !Schema::hasTable('aero_credits_types')
            || !Schema::hasTable('aero_credits_actions')) {
            return;
        }

        $type = \Aero\Credits\Models\CreditType::where('code', 'azul')->first()
            ?: \Aero\Credits\Models\CreditType::orderBy('sort_order')->first();

        if (!$type) {
            return;
        }

        \Aero\Credits\Models\CreditAction::firstOrCreate(
            ['code' => '{p}.{accion}'],
            [
                'label'          => '{Descripción de la acción}',
                'plugin'         => 'Aero.{Plugin}',
                'credit_type_id' => $type->id,
                'default_cost'   => 1,
            ]
        );
    }
};
