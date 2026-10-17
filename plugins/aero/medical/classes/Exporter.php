<?php namespace Aero\Medical\Classes;

use Aero\Medical\Models\Addendum;
use Aero\Medical\Models\Analyte;
use Aero\Medical\Models\AuditLog;
use Aero\Medical\Models\Consultation;
use Aero\Medical\Models\Diagnosis;
use Aero\Medical\Models\DiagnosisCatalog;
use Aero\Medical\Models\Deworming;
use Aero\Medical\Models\GroomingVisit;
use Aero\Medical\Models\DocumentTemplate;
use Aero\Medical\Models\ExamCatalog;
use Aero\Medical\Models\LabResult;
use Aero\Medical\Models\MedicalDocument;
use Aero\Medical\Models\MedicalFile;
use Aero\Medical\Models\Medication;
use Aero\Medical\Models\NoteTemplate;
use Aero\Medical\Models\Order;
use Aero\Medical\Models\Patient;
use Aero\Medical\Models\Prescription;
use Aero\Medical\Models\Questionnaire;
use Aero\Medical\Models\QuestionnaireAnswer;
use Aero\Medical\Models\Range;
use Aero\Medical\Models\Vaccination;
use Aero\Medical\Models\Vaccine;
use Aero\Medical\Models\Vitals;

/**
 * Exportación completa: los datos del paciente y del negocio son SIEMPRE del negocio. Genera un ZIP con
 * JSON legible por máquina (todo, ya descifrado) y los archivos originales. Un archivo ilegible o
 * ausente no aborta la exportación: queda anotado en `avisos`. Devuelve la ruta de un temporal que
 * quien llama debe borrar tras la descarga.
 */
class Exporter
{
    public static function patient(Patient $p): string
    {
        return static::zip(fn (\ZipArchive $zip, string $tmp, array &$warnings) => static::addPatient($zip, $tmp, $p, '', $warnings), 'paciente-' . $p->id);
    }

    public static function business(int $tenantId): string
    {
        return static::zip(function (\ZipArchive $zip, string $tmp, array &$warnings) use ($tenantId) {
            $index = [];
            foreach (Patient::withTrashed()->where('tenant_id', $tenantId)->orderBy('id')->cursor() as $p) {
                $dir = 'pacientes/' . $p->id . '-' . \Str::slug($p->name ?: 'paciente') . '/';
                static::addPatient($zip, $tmp, $p, $dir, $warnings);
                $index[] = ['id' => $p->id, 'nombre' => $p->name, 'documento' => $p->document, 'carpeta' => $dir, 'eliminado' => (bool) $p->deleted_at];
            }
            $zip->addFromString('pacientes.json', static::json($index));
            $zip->addFromString('negocio.json', static::json(static::businessData($tenantId)));
            $zip->addFromString('bitacora.json', static::json(AuditLog::where('tenant_id', $tenantId)->orderBy('id')->get()->toArray()));

            // Archivos de importaciones sin asociar (siguen siendo del negocio).
            foreach (MedicalFile::where('tenant_id', $tenantId)->whereNull('patient_id')->get() as $f) {
                static::addFile($zip, $tmp, $f, 'sin-asociar/', $warnings);
            }
        }, 'negocio-' . $tenantId);
    }

    protected static function zip(callable $fill, string $name): string
    {
        @set_time_limit(0);
        $tmp = sys_get_temp_dir() . '/aero-medical-export-' . bin2hex(random_bytes(8));
        mkdir($tmp, 0700, true);
        $path = $tmp . '.zip';

        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::CREATE) !== true) {
            throw new MedicalException('No se pudo crear el archivo de exportación.');
        }

        $warnings = [];
        try {
            $fill($zip, $tmp, $warnings);
            $zip->addFromString('LEEME.txt', "Exportación de Historia clínica ({$name}) generada el " . now()->format('d/m/Y H:i') . ".\n"
                . "Los datos están en JSON (UTF-8); los archivos originales en las carpetas «archivos».\n"
                . "Estos datos pertenecen al negocio.\n" . ($warnings ? "\nAvisos:\n- " . implode("\n- ", $warnings) . "\n" : ''));
            $zip->close();
        } finally {
            static::rrmdir($tmp);
        }

        return $path;
    }

    protected static function addPatient(\ZipArchive $zip, string $tmp, Patient $p, string $dir, array &$warnings): void
    {
        $tenantId = (int) $p->tenant_id;
        $notes = Consultation::withTrashed()->where('tenant_id', $tenantId)->where('patient_id', $p->id)->orderBy('consulted_at')->orderBy('id')->get();

        $data = [
            'paciente' => static::model($p, ['customer_id']) + ['contacto' => $p->customer ? ['nombre' => $p->customer->name, 'telefono' => $p->customer->phone, 'correo' => $p->customer->email] : null],
            'notas' => $notes->map(function ($c) {
                $vit = Vitals::where('consultation_id', $c->id)->first();
                $rx = Prescription::with('items')->where('consultation_id', $c->id)->first();
                $ord = Order::with('items')->where('consultation_id', $c->id)->first();

                return [
                    'id' => $c->id, 'tipo' => $c->kind, 'estado' => $c->status, 'fecha' => $c->consulted_at?->toIso8601String(), 'profesional' => $c->professional_name,
                    'firmada' => $c->signed_at?->toIso8601String(), 'huella' => $c->content_hash, 'integra' => $c->isSigned() ? $c->integrityOk() : null,
                    'plantilla' => $c->template_name, 'secciones' => $c->sectionList(), 'contenido' => (array) $c->content,
                    'signos_vitales' => $vit ? static::model($vit, ['id', 'tenant_id', 'patient_id', 'consultation_id', 'created_at', 'updated_at']) : null,
                    'diagnosticos' => Diagnosis::where('consultation_id', $c->id)->orderBy('position')->get()->map(fn ($d) => static::model($d, ['id', 'tenant_id', 'patient_id', 'consultation_id', 'created_at', 'updated_at']))->all(),
                    'receta' => $rx ? ['notas' => $rx->notes, 'medicamentos' => $rx->items->map(fn ($i) => static::model($i, ['id', 'tenant_id', 'prescription_id', 'created_at', 'updated_at']))->all()] : null,
                    'ordenes' => $ord ? ['notas' => $ord->notes, 'examenes' => $ord->items->map(fn ($i) => static::model($i, ['id', 'tenant_id', 'order_id', 'created_at', 'updated_at']))->all()] : null,
                    'cuestionarios' => QuestionnaireAnswer::where('consultation_id', $c->id)->get()->map(fn ($a) => [
                        'cuestionario' => $a->questionnaire_name, 'puntaje' => $a->score, 'interpretacion' => $a->interpretation, 'respuestas' => $a->readable(), 'fecha' => $a->answered_at?->toIso8601String(),
                    ])->all(),
                    'adendas' => Addendum::where('consultation_id', $c->id)->orderBy('id')->get()->map(fn ($a) => ['autor' => $a->user_name, 'fecha' => $a->created_at?->toIso8601String(), 'texto' => $a->body])->all(),
                ];
            })->all(),
            'vacunas' => Vaccination::where('tenant_id', $tenantId)->where('patient_id', $p->id)->orderBy('applied_on')->get()->map(fn ($v) => static::model($v, ['id', 'tenant_id', 'patient_id', 'created_at', 'updated_at']))->all(),
            'laboratorio' => LabResult::where('tenant_id', $tenantId)->where('patient_id', $p->id)->orderBy('measured_on')->get()->map(fn ($l) => static::model($l, ['id', 'tenant_id', 'patient_id', 'created_at', 'updated_at']))->all(),
            'desparasitaciones' => Deworming::where('tenant_id', $tenantId)->where('patient_id', $p->id)->orderBy('applied_on')->get()->map(fn ($d) => static::model($d, ['id', 'tenant_id', 'patient_id', 'created_at', 'updated_at']))->all(),
            'peluqueria' => GroomingVisit::withTrashed()->where('tenant_id', $tenantId)->where('patient_id', $p->id)->orderBy('visited_on')->get()->map(fn ($g) => static::model($g, ['tenant_id', 'patient_id', 'created_at', 'updated_at']) + ['eliminada' => (bool) $g->deleted_at])->all(),
            'documentos' => MedicalDocument::withTrashed()->where('tenant_id', $tenantId)->where('patient_id', $p->id)->orderBy('id')->get()->map(fn ($d) => [
                'tipo' => $d->kind, 'titulo' => $d->title, 'estado' => $d->status, 'emitido' => $d->issued_at?->toIso8601String(), 'profesional' => $d->professional_name, 'texto' => $d->body, 'huella' => $d->content_hash,
            ])->all(),
        ];

        $files = MedicalFile::withTrashed()->where('tenant_id', $tenantId)->where('patient_id', $p->id)->orderBy('id')->get();
        $data['archivos'] = $files->map(fn ($f) => ['id' => $f->id, 'tipo' => $f->kind, 'titulo' => $f->title, 'nombre_original' => $f->original_name, 'mime' => $f->mime, 'visita_peluqueria' => $f->grooming_id, 'fecha' => $f->taken_on?->toDateString(), 'notas' => $f->notes, 'sha256' => $f->sha256, 'eliminado' => (bool) $f->deleted_at])->all();

        $zip->addFromString($dir . 'paciente.json', static::json($data));
        foreach ($files as $f) {
            static::addFile($zip, $tmp, $f, $dir . 'archivos/', $warnings);
        }
    }

    protected static function addFile(\ZipArchive $zip, string $tmp, MedicalFile $f, string $dir, array &$warnings): void
    {
        try {
            $local = $tmp . '/f' . $f->id;
            file_put_contents($local, $f->contents());
            $name = $f->id . '-' . PrivateFiles::cleanName((string) $f->original_name);
            $zip->addFile($local, $dir . $name);
        } catch (\Throwable $e) {
            $warnings[] = "Archivo {$f->id} no incluido: " . $e->getMessage();
        }
    }

    protected static function businessData(int $tenantId): array
    {
        $plain = fn ($q) => $q->get()->map(fn ($m) => static::model($m, ['tenant_id']))->all();

        return [
            'configuracion' => static::model(\Aero\Medical\Models\ClinicSettings::where('tenant_id', $tenantId)->first() ?? new \Aero\Medical\Models\ClinicSettings(), ['tenant_id']),
            'plantillas_de_nota' => $plain(NoteTemplate::where('tenant_id', $tenantId)),
            'formatos_de_documento' => $plain(DocumentTemplate::where('tenant_id', $tenantId)),
            'diagnosticos_del_negocio' => $plain(DiagnosisCatalog::where('tenant_id', $tenantId)),
            'medicamentos' => $plain(Medication::where('tenant_id', $tenantId)),
            'examenes' => $plain(ExamCatalog::where('tenant_id', $tenantId)),
            'analitos' => $plain(Analyte::where('tenant_id', $tenantId)),
            'cuestionarios' => $plain(Questionnaire::where('tenant_id', $tenantId)),
            'vacunas' => $plain(Vaccine::where('tenant_id', $tenantId)),
            'rangos' => $plain(Range::where('tenant_id', $tenantId)),
        ];
    }

    /** Atributos ya descifrados (los casts encrypted se aplican al leerlos). */
    protected static function model($m, array $except = []): array
    {
        return array_diff_key($m->attributesToArray(), array_flip($except + ['deleted_at' => 'deleted_at']));
    }

    protected static function json($v): string
    {
        return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    protected static function rrmdir(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $f) {
            is_dir($f) ? static::rrmdir($f) : @unlink($f);
        }
        @rmdir($dir);
    }
}
