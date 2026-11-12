<?php

use Aero\Notify\Classes\EventSeeder;
use October\Rain\Database\Updates\Seeder;

/**
 * `support.ticket.agent_replied` ahora avisa también por campana (inapp) y push,
 * además del correo. EventSeeder refresca default_channels y crea solo las
 * reglas globales que faltan (no pisa las existentes).
 */
return new class extends Seeder
{
    public function run(): void
    {
        (new EventSeeder)->run();
    }
};
