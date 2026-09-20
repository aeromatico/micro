<?php

use Aero\Notify\Classes\DefaultTemplates;
use Aero\Notify\Classes\EventSeeder;
use October\Rain\Database\Updates\Seeder;

/**
 * El catálogo suma 'push' junto a cada 'inapp' (reglas nuevas vía reseed) y se
 * siembran plantillas globales para todas las reglas que no tenían.
 */
return new class extends Seeder
{
    public function run(): void
    {
        (new EventSeeder)->run();
        DefaultTemplates::seedMissing();
    }
};
