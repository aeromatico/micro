<?php

use Aero\Credits\Classes\CreditActionCatalog;
use Aero\Credits\Models\CreditAction;
use Aero\Credits\Models\CreditType;
use October\Rain\Database\Updates\Seeder;

/**
 * Siembra los dos colores base (azul = 0.01 USD, rojo = 0.02 USD, "premium")
 * y el catálogo de acciones facturables de fábrica (ver CreditActionCatalog).
 * Idempotente: upsert por `code`, no pisa valores que el superadmin ya haya
 * ajustado desde el backend.
 */
return new class extends Seeder
{
    public function run(): void
    {
        $azul = CreditType::firstOrCreate(
            ['code' => 'azul'],
            ['label' => 'Azul', 'color' => '#3b82f6', 'usd_value' => 0.0100, 'low_balance_threshold' => 50, 'sort_order' => 1]
        );

        $rojo = CreditType::firstOrCreate(
            ['code' => 'rojo'],
            ['label' => 'Rojo', 'color' => '#ef4444', 'usd_value' => 0.0200, 'low_balance_threshold' => 20, 'sort_order' => 2]
        );

        $typesByCode = ['azul' => $azul, 'rojo' => $rojo];

        foreach (CreditActionCatalog::defaults() as $definition) {
            $type = $typesByCode[$definition['type']] ?? $azul;

            CreditAction::firstOrCreate(
                ['code' => $definition['code']],
                [
                    'label'          => $definition['label'],
                    'plugin'         => $definition['plugin'],
                    'credit_type_id' => $type->id,
                    'default_cost'   => $definition['default_cost'],
                ]
            );
        }
    }
};
