<?php

use Aero\Credits\Models\CreditAction;
use Aero\Credits\Models\CreditType;
use October\Rain\Database\Updates\Seeder;

/**
 * Acción facturable "mensaje de WhatsApp enviado por API" (Aero.Hello):
 * color naranja (tier de entrada), 1 crédito por mensaje. Idempotente: no
 * pisa el costo que el superadmin ya haya ajustado.
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
            ['code' => 'hello.message_send'],
            [
                'label'          => 'Mensaje de WhatsApp enviado por API',
                'plugin'         => 'Aero.Hello',
                'credit_type_id' => $type->id,
                'default_cost'   => 1,
            ]
        );
    }
};
