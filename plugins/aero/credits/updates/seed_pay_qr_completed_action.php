<?php

use Aero\Credits\Models\CreditAction;
use Aero\Credits\Models\CreditType;
use October\Rain\Database\Updates\Seeder;

/**
 * Acción facturable "QR de cobro completado" (Aero.Pay): 8 monedas de bronce
 * por cada QR que pasa a pagado. Idempotente: no pisa el costo que el
 * superadmin ya haya ajustado.
 */
return new class extends Seeder
{
    public function run(): void
    {
        $type = CreditType::where('code', 'bronce')->first() ?: CreditType::orderBy('sort_order')->first();

        if (!$type) {
            return;
        }

        CreditAction::firstOrCreate(
            ['code' => 'pay.qr_completed'],
            [
                'label'          => 'QR de cobro completado (pagado)',
                'plugin'         => 'Aero.Pay',
                'credit_type_id' => $type->id,
                'default_cost'   => 8,
            ]
        );
    }
};
