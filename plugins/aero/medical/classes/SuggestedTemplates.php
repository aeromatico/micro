<?php namespace Aero\Medical\Classes;

use Aero\Medical\Models\NoteTemplate;

/**
 * Plantillas sugeridas que el negocio agrega con un botón: control de enfermedad crónica y chequeo preventivo.
 * Son solo ESTRUCTURA de secciones (sin contenido clínico, sin cifras); el negocio las edita como cualquier otra.
 */
class SuggestedTemplates
{
    /** @return array<string,array{kind:string,name:string,sections:array}> */
    public static function all(?string $mode): array
    {
        $animal = $mode === Mode::ANIMAL;
        $vitals = ['key' => 'vitals', 'label' => 'Signos vitales', 'type' => 'vitals', 'rows' => 0];
        $t = fn (string $key, string $label, int $rows = 3) => ['key' => $key, 'label' => $label, 'type' => 'text', 'rows' => $rows];

        return [
            'chronic' => [
                'kind' => 'control',
                'name' => $animal ? 'Control de enfermedad crónica (animal)' : 'Control de paciente crónico',
                'sections' => [
                    $t('reason', 'Motivo del control', 2),
                    $t('adherence', $animal ? 'Cumplimiento del tratamiento por el tutor y efectos adversos' : 'Adherencia al tratamiento y efectos adversos'),
                    $t('evolution', 'Evolución desde el último control', 4),
                    $vitals,
                    $t('physical_exam', 'Examen físico', 4),
                    $t('labs_review', 'Laboratorio y estudios revisados'),
                    $t('treatment_changes', 'Ajustes de tratamiento'),
                    $t('plan', 'Plan y próximo control', 3),
                ],
            ],
            'preventive' => [
                'kind' => 'consultation',
                'name' => $animal ? 'Chequeo anual preventivo (animal)' : 'Chequeo preventivo',
                'sections' => [
                    $t('reason', 'Motivo del chequeo', 2),
                    $t('risk_factors', $animal ? 'Alimentación, actividad y convivencia' : 'Hábitos y factores de riesgo'),
                    $t('history_update', $animal ? 'Antecedentes y medicación actualizados' : 'Antecedentes y medicación actualizados'),
                    $vitals,
                    $t('physical_exam', 'Examen físico', 4),
                    $t('pending_screening', $animal ? 'Vacunas y desparasitación pendientes' : 'Tamizajes y vacunas pendientes'),
                    $t('counseling', $animal ? 'Recomendaciones al tutor' : 'Consejería'),
                    $t('plan', 'Plan y próximo chequeo', 3),
                ],
            ],
        ];
    }

    /** Agrega las que el negocio aún no tiene (por nombre). @return array{created:int, skipped:int} */
    public static function load(int $tenantId): array
    {
        $mode = Mode::forTenant($tenantId);
        if (!$mode) {
            throw new MedicalException('Elija primero el modo del negocio.');
        }

        $created = $skipped = 0;
        foreach (self::all($mode) as $def) {
            if (NoteTemplate::where('tenant_id', $tenantId)->where('name', $def['name'])->exists()) {
                $skipped++;
                continue;
            }
            NoteTemplate::create(['tenant_id' => $tenantId, 'kind' => $def['kind'], 'name' => $def['name'], 'sections' => $def['sections'], 'is_active' => true]);
            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }
}
