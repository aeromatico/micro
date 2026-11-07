<?php namespace Aero\Clinica\Classes;

use Aero\Clinica\Models\Analyte;
use Aero\Clinica\Models\Range;

/**
 * Rangos de REFERENCIA para personas adultas, solo para arrancar: el negocio los carga con un botón (nunca
 * automático), quedan marcados como borrador y deben ser revisados por un médico antes de confiar en el
 * rojo de la hoja. No incluyen niños, embarazo ni valores que dependen del sexo o del laboratorio
 * (hemoglobina, creatinina…): esos los define el negocio con las referencias de su laboratorio.
 *
 * Cada fila es el rango NORMAL: se marca ▼ por debajo de `low` y ▲ por encima de `high`.
 * Fuentes: ver README.md del módulo (OMS, ESC/ESH, ADA, ATP III).
 */
class ReferenceRanges
{
    /** metric => [low, high, descripción, fuente]. Los analitos se enlazan por nombre con los del negocio. */
    public const VITALS = [
        'systolic'      => [90, 139, 'Presión sistólica (mmHg)', 'OMS / ESC-ESH: hipertensión desde 140; hipotensión por debajo de 90'],
        'diastolic'     => [60, 89, 'Presión diastólica (mmHg)', 'OMS / ESC-ESH: hipertensión desde 90; hipotensión por debajo de 60'],
        'heart_rate'    => [60, 100, 'Frecuencia cardíaca en reposo (lpm)', 'Rango habitual del adulto en reposo'],
        'resp_rate'     => [12, 20, 'Frecuencia respiratoria (rpm)', 'Rango habitual del adulto en reposo'],
        'temperature_c' => [36.0, 37.5, 'Temperatura (°C)', 'Rango habitual; fiebre desde 38'],
        'spo2'          => [95, null, 'Saturación de oxígeno (%)', 'Valor habitual a nivel del mar'],
        'bmi'           => [18.5, 24.9, 'IMC (kg/m²)', 'OMS: adulto normopeso 18,5–24,9'],
    ];

    /** nombre del analito por defecto => [low, high, fuente] */
    public const ANALYTES = [
        'Glucosa en ayunas'               => [70, 99, 'ADA / OMS: glucosa en ayunas alterada desde 100 mg/dL'],
        'Hemoglobina glicosilada (HbA1c)' => [null, 5.6, 'ADA: normal por debajo de 5,7 %'],
        'Colesterol total'                => [null, 199, 'ATP III: deseable por debajo de 200 mg/dL'],
        'Triglicéridos'                   => [null, 149, 'ATP III: normal por debajo de 150 mg/dL'],
    ];

    /**
     * Crea los rangos de referencia que el negocio todavía no tiene (por medición). No toca ni
     * reemplaza ninguno existente.
     * @return array{created:int, skipped:int}
     */
    public static function load(int $tenantId): array
    {
        Analyte::ensureDefaults($tenantId);
        $analytes = Analyte::forTenant($tenantId)->get()->keyBy(fn ($a) => mb_strtolower($a->name));

        $rows = [];
        foreach (self::VITALS as $metric => [$low, $high]) {
            $rows[$metric] = [$low, $high];
        }
        foreach (self::ANALYTES as $name => [$low, $high]) {
            if ($a = $analytes[mb_strtolower($name)] ?? null) {
                $rows['analyte:' . $a->id] = [$low, $high];
            }
        }

        $created = $skipped = 0;
        foreach ($rows as $metric => [$low, $high]) {
            if (Range::where('tenant_id', $tenantId)->where('metric', $metric)->whereNull('sex')->exists()) {
                $skipped++;
                continue;
            }
            $r = new Range(['tenant_id' => $tenantId, 'metric' => $metric, 'low' => $low, 'high' => $high]);
            $r->source = 'reference';
            $r->save();
            $created++;
        }
        Ranges::flush();

        return ['created' => $created, 'skipped' => $skipped];
    }
}
