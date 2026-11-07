<?php namespace Aero\Clinica\Classes;

use Aero\Clinica\Models\DocumentTemplate;

/**
 * Formatos médicos de partida: certificado, constancia, informe, consentimiento informado y derivación.
 * Son solo texto con variables ({{paciente}}, {{fecha}}…): sin cifras ni afirmaciones clínicas. El negocio los
 * revisa y edita; lo que no se conoce al generar queda en blanco para completar a mano.
 */
class DefaultDocuments
{
    /** @return array<string,array{kind:string,name:string,body:string}> */
    public static function all(): array
    {
        return [
            'certificate' => [
                'kind' => 'certificate', 'name' => 'Certificado médico',
                'body' => "CERTIFICADO MÉDICO\n\nYo, {{profesional}}, {{especialidad}}, matrícula {{matricula}}, certifico que el/la paciente {{paciente}}, con documento {{documento}}, de {{edad}} de edad, fue evaluado/a en consulta el {{fecha}}.\n\nDiagnóstico: {{diagnostico}}\n\nSe extiende el presente certificado a solicitud del interesado/a para los fines que estime convenientes, con __________ días de reposo a partir de la fecha.\n\n{{negocio}}, {{fecha}}",
            ],
            'constancia' => [
                'kind' => 'constancia', 'name' => 'Constancia de atención',
                'body' => "CONSTANCIA DE ATENCIÓN\n\nSe hace constar que {{paciente}}, con documento {{documento}}, fue atendido/a en {{negocio}} el {{fecha}} a las {{hora}}.\n\nMotivo de consulta: {{motivo}}\n\nProfesional: {{profesional}}, matrícula {{matricula}}.",
            ],
            'report' => [
                'kind' => 'report', 'name' => 'Informe médico',
                'body' => "INFORME MÉDICO\n\nPaciente: {{paciente}}  ·  Documento: {{documento}}  ·  Edad: {{edad}}  ·  Sexo: {{sexo}}\nFecha: {{fecha}}\n\nMotivo de consulta: {{motivo}}\n\nDiagnósticos: {{diagnosticos}}\n\nResumen clínico y evolución:\n__________\n\nTratamiento y recomendaciones:\n__________\n\n{{profesional}} · Matrícula {{matricula}}",
            ],
            'consent' => [
                'kind' => 'consent', 'name' => 'Consentimiento informado',
                'body' => "CONSENTIMIENTO INFORMADO\n\nYo, {{paciente}}, con documento {{documento}}, declaro que el/la profesional {{profesional}} me explicó en términos que comprendo el procedimiento: __________, su finalidad, los beneficios esperados, los riesgos posibles y las alternativas disponibles, y que tuve la oportunidad de hacer preguntas.\n\nAutorizo voluntariamente su realización y sé que puedo retirar este consentimiento antes del procedimiento.\n\nLugar y fecha: {{negocio}}, {{fecha}}",
            ],
            'referral' => [
                'kind' => 'referral', 'name' => 'Derivación a especialista',
                'body' => "DERIVACIÓN\n\nA: __________ (especialidad / centro)\n\nSe deriva al/la paciente {{paciente}}, documento {{documento}}, {{edad}}, sexo {{sexo}}.\n\nMotivo de la derivación: __________\n\nDiagnóstico: {{diagnostico}}\n\nSe solicita: evaluación y conducta a seguir. Se adjuntan estudios disponibles: __________\n\n{{profesional}} · {{especialidad}} · Matrícula {{matricula}}\n{{negocio}}, {{fecha}}",
            ],
        ];
    }

    /** Agrega los que el negocio aún no tiene (por nombre). @return array{created:int, skipped:int} */
    public static function load(int $tenantId): array
    {
        $created = $skipped = 0;
        foreach (self::all() as $def) {
            if (DocumentTemplate::where('tenant_id', $tenantId)->where('name', $def['name'])->exists()) {
                $skipped++;
                continue;
            }
            DocumentTemplate::create(['tenant_id' => $tenantId, 'kind' => $def['kind'], 'name' => $def['name'], 'body' => $def['body'], 'is_active' => true]);
            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }
}
