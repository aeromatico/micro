<?php

use Aero\Credits\Models\CreditType;
use October\Rain\Database\Updates\Seeder;

/**
 * Tercer color de crédito, de menor valor que azul/rojo (pensado como "tier"
 * de entrada, ej. para acciones de bajo costo como tool-calling de Súper
 * IA). Idempotente: `firstOrCreate` por `code`, no pisa valores que el
 * superadmin ya haya ajustado desde el backend.
 */
return new class extends Seeder
{
    public function run(): void
    {
        CreditType::firstOrCreate(
            ['code' => 'naranja'],
            ['label' => 'Naranja', 'color' => '#f97316', 'usd_value' => 0.0050, 'low_balance_threshold' => 100, 'sort_order' => 0]
        );
    }
};
