<?php

use Aero\Workspaces\Models\Staff;
use October\Rain\Database\Updates\Seeder;

/**
 * El gráfico de capacidades de Mary venía de la carga inicial de contenido (creatividad 94, viralidad 88…) y no
 * describía su trabajo. Se ajusta al de una asesora de comercio electrónico y de operación de restaurantes (muy
 * eficiente y ejecutora, solvente en textos y campañas), y el de Gus al de un productor multimedia. Solo se cambia si
 * todavía tienen los valores heredados, para respetar lo que se edite a mano en el backend.
 */
return new class extends Seeder
{
    public const MARY_OLD = ['creatividad' => 94, 'viralidad' => 88, 'ejecucion' => 90, 'narrativa' => 92, 'estetica' => 91, 'eficiencia' => 76];
    public const MARY_NEW = ['creatividad' => 70, 'viralidad' => 66, 'ejecucion' => 90, 'narrativa' => 78, 'estetica' => 62, 'eficiencia' => 94];
    public const GUS_OLD = ['creatividad' => 76, 'viralidad' => 58, 'ejecucion' => 90, 'narrativa' => 82, 'estetica' => 86, 'eficiencia' => 84];
    public const GUS_NEW = ['creatividad' => 90, 'viralidad' => 80, 'ejecucion' => 84, 'narrativa' => 90, 'estetica' => 92, 'eficiencia' => 76];

    public function run(): void
    {
        // Directo a la base: la columna es JSON y el modelo la reescribe (setter + jsonable) de forma poco fiable al guardar.
        foreach ([['Mary', static::MARY_OLD, static::MARY_NEW], ['Gus', static::GUS_OLD, static::GUS_NEW]] as [$name, $old, $new]) {
            $row = \Illuminate\Support\Facades\DB::table('aero_workspaces_staff')->where('name', $name)->first(['id', 'capabilities']);

            if ($row && (array) json_decode((string) $row->capabilities, true) == $old) {
                \Illuminate\Support\Facades\DB::table('aero_workspaces_staff')->where('id', $row->id)
                    ->update(['capabilities' => json_encode($new, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);
            }
        }
    }
};
