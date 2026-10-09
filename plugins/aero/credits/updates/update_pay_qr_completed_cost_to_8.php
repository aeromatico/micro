<?php

use Aero\Credits\Models\CreditAction;
use October\Rain\Database\Updates\Seeder;

/**
 * La tarifa por QR completado pasa de 3 a 8 monedas de bronce. Solo toca la
 * acción si el superadmin no la había ajustado (sigue en 3).
 */
return new class extends Seeder
{
    public function run(): void
    {
        CreditAction::where('code', 'pay.qr_completed')
            ->where('default_cost', 3)
            ->update(['default_cost' => 8]);
    }
};
