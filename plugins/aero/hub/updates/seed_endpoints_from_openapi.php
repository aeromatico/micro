<?php

use Aero\Hub\Classes\CatalogSync;
use October\Rain\Database\Updates\Seeder;

/**
 * Puebla las ~155 filas iniciales del catálogo desde el snapshot local del
 * spec (resources/yepapi_openapi.json) — sin red durante la instalación.
 * Todas quedan inactivas por defecto: el superadmin revisa precio/color
 * antes de habilitar cada una (ver Aero\Hub\Classes\CatalogSync).
 */
return new class extends Seeder
{
    public function run(): void
    {
        CatalogSync::run(false);
    }
};
