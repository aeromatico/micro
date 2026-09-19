<?php

use October\Rain\Database\Updates\Seeder;

/**
 * Acción facturable "segmento de SMS": 1 crédito por segmento (un SMS largo
 * son varios segmentos). Idempotente; no pisa el costo que el superadmin ya
 * haya ajustado. Sin Aero.Credits instalado no hace nada.
 */
return new class extends Seeder
{
    public function run(): void
    {
        if (!class_exists(\Aero\Credits\Models\CreditAction::class)) {
            return;
        }

        $type = \Aero\Credits\Models\CreditType::where('code', 'azul')->first()
            ?: \Aero\Credits\Models\CreditType::orderBy('sort_order')->first();

        if (!$type) {
            return;
        }

        \Aero\Credits\Models\CreditAction::firstOrCreate(
            ['code' => 'sms.segment'],
            [
                'label'          => 'Segmento de SMS enviado',
                'plugin'         => 'Aero.Sms',
                'credit_type_id' => $type->id,
                'default_cost'   => 5,
            ]
        );
    }
};
