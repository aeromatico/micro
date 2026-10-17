<?php namespace Aero\Medical\Classes;

use Aero\Medical\Models\Consultation;
use Aero\Medical\Models\Diagnosis;
use Aero\Medical\Models\Patient;
use Aero\Medical\Models\Professional;

/**
 * Variables de los formatos de documento. Texto plano con {{variable}}: se resuelven al generar y el
 * documento emitido guarda el texto ya resuelto. Una variable desconocida se rechaza al guardar el formato
 * (no se imprime «{{typo}}» en un certificado); una conocida sin dato sale como línea en blanco.
 */
class DocumentRenderer
{
    public const BLANK = '__________';

    /** variable => descripción (para la pantalla de formatos). */
    public const VARIABLES = [
        'paciente' => 'Nombre del paciente', 'documento' => 'Documento de identidad', 'edad' => 'Edad', 'fecha_nacimiento' => 'Fecha de nacimiento',
        'sexo' => 'Sexo', 'tutor' => 'Tutor / persona de contacto (cliente de Reservas)', 'especie' => 'Especie (animal)', 'raza' => 'Raza (animal)',
        'microchip' => 'Microchip (animal)', 'fecha' => 'Fecha de hoy', 'hora' => 'Hora actual', 'profesional' => 'Profesional que emite',
        'matricula' => 'Matrícula del profesional', 'especialidad' => 'Especialidad del profesional', 'negocio' => 'Nombre del negocio',
        'diagnostico' => 'Diagnóstico principal de la nota (o de la última firmada)', 'diagnosticos' => 'Todos los diagnósticos de la nota',
        'motivo' => 'Motivo de consulta de la nota',
    ];

    public static function unknownVariables(string $body): array
    {
        preg_match_all('/\{\{\s*([^}]*?)\s*\}\}/', $body, $m);

        return array_values(array_unique(array_filter($m[1], fn ($v) => !array_key_exists($v, self::VARIABLES))));
    }

    public static function render(string $body, Patient $p, $user, ?Consultation $c = null): string
    {
        $values = self::values($p, $user, $c);

        return preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/', function ($m) use ($values) {
            $v = trim((string) ($values[$m[1]] ?? ''));

            return $v !== '' ? $v : self::BLANK;
        }, $body);
    }

    /** Nombre del negocio (Office o tenant). Un fallo al leerlo no debe impedir generar el documento. */
    public static function businessName(int $tenantId): string
    {
        try {
            if (class_exists(\Aero\Office\Models\OfficeSettings::class) && ($n = \Aero\Office\Models\OfficeSettings::where('tenant_id', $tenantId)->value('business_name'))) {
                return (string) $n;
            }
            if (class_exists(\Aero\Sites\Models\Tenant::class)) {
                return (string) \Aero\Sites\Models\Tenant::where('id', $tenantId)->value('name');
            }
        } catch (\Throwable $e) {
            // sin nombre: el documento sale igual
        }

        return '';
    }

    public static function values(Patient $p, $user, ?Consultation $c = null): array
    {
        $tenantId = (int) $p->tenant_id;
        $profile = Professional::forUser($tenantId, $user);
        $note = $c ?? Consultation::where('tenant_id', $tenantId)->where('patient_id', $p->id)->where('status', 'signed')->orderByDesc('consulted_at')->orderByDesc('id')->first();
        $dx = $note ? Diagnosis::where('consultation_id', $note->id)->orderByDesc('is_primary')->orderBy('position')->get() : collect();

        $sex = ['male' => $p->kind === Mode::ANIMAL ? 'Macho' : 'Masculino', 'female' => $p->kind === Mode::ANIMAL ? 'Hembra' : 'Femenino'][$p->sex] ?? '';
        $business = static::businessName($tenantId);

        return [
            'paciente' => $p->name, 'documento' => (string) $p->document, 'edad' => (string) $p->age_label,
            'fecha_nacimiento' => $p->birth_date ? $p->birth_date->format('d/m/Y') : '', 'sexo' => $sex,
            'tutor' => (string) ($p->customer?->name ?? ''), 'especie' => $p->kind === Mode::ANIMAL ? (Mode::species()[$p->species] ?? '') : '',
            'raza' => $p->kind === Mode::ANIMAL ? (string) $p->breed : '', 'microchip' => $p->kind === Mode::ANIMAL ? (string) $p->microchip : '',
            'fecha' => now()->locale('es')->translatedFormat('d \d\e F \d\e Y'), 'hora' => now()->format('H:i'),
            'profesional' => $profile->full_name, 'matricula' => (string) $profile->license, 'especialidad' => (string) $profile->specialty,
            'negocio' => $business,
            'diagnostico' => $dx->first()?->display ?? '', 'diagnosticos' => $dx->map->display->implode('; '),
            'motivo' => $note ? trim((string) (((array) $note->content)['reason'] ?? '')) : '',
        ];
    }
}
