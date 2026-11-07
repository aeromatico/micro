<?php namespace Aero\Clinica\Classes;

/**
 * Plantillas de nota que se siembran para cada negocio la primera vez. Son
 * solo estructura de secciones (sin contenido clínico); el negocio las edita.
 * type: text | vitals.
 */
class DefaultTemplates
{
    public const KINDS = ['consultation' => 'Consulta', 'control' => 'Control'];

    public static function sections(string $kind): array
    {
        if ($kind === 'control') {
            return [
                ['key' => 'reason', 'label' => 'Motivo del control', 'type' => 'text', 'rows' => 2],
                ['key' => 'evolution', 'label' => 'Evolución', 'type' => 'text', 'rows' => 4],
                ['key' => 'vitals', 'label' => 'Signos vitales', 'type' => 'vitals', 'rows' => 0],
                ['key' => 'physical_exam', 'label' => 'Examen físico', 'type' => 'text', 'rows' => 4],
                ['key' => 'plan', 'label' => 'Plan', 'type' => 'text', 'rows' => 4],
            ];
        }

        return [
            ['key' => 'reason', 'label' => 'Motivo de consulta', 'type' => 'text', 'rows' => 2],
            ['key' => 'current_illness', 'label' => 'Enfermedad actual', 'type' => 'text', 'rows' => 4],
            ['key' => 'systems_review', 'label' => 'Revisión por sistemas', 'type' => 'text', 'rows' => 4],
            ['key' => 'vitals', 'label' => 'Signos vitales', 'type' => 'vitals', 'rows' => 0],
            ['key' => 'physical_exam', 'label' => 'Examen físico', 'type' => 'text', 'rows' => 4],
            ['key' => 'impression', 'label' => 'Impresión diagnóstica', 'type' => 'text', 'rows' => 3],
            ['key' => 'treatment', 'label' => 'Tratamiento', 'type' => 'text', 'rows' => 3],
            ['key' => 'plan', 'label' => 'Plan y seguimiento', 'type' => 'text', 'rows' => 3],
        ];
    }
}
