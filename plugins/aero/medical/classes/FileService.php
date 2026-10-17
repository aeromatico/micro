<?php namespace Aero\Medical\Classes;

use Aero\Medical\Models\Consultation;
use Aero\Medical\Models\LabResult;
use Aero\Medical\Models\MedicalFile;
use Aero\Medical\Models\Patient;
use Illuminate\Http\UploadedFile;

/** Estudios del paciente (laboratorio e imágenes) con resultados numéricos opcionales que alimentan la hoja. */
class FileService
{
    /**
     * @param array{kind?:string,title?:string,taken_on?:string,consultation_id?:mixed,notes?:string,results?:array} $in
     */
    public function attach(Patient $p, UploadedFile $file, array $in, $user): MedicalFile
    {
        $kind = in_array($in['kind'] ?? '', ['lab', 'imaging', 'history', 'other'], true) ? $in['kind'] : 'other';
        $tenantId = (int) $p->tenant_id;

        $consultationId = null;
        if (!empty($in['consultation_id'])) {
            $c = Consultation::where('tenant_id', $tenantId)->where('patient_id', $p->id)->find((int) $in['consultation_id']);
            if (!$c) {
                throw new MedicalException('La nota elegida no corresponde a este paciente.');
            }
            $consultationId = $c->id;
        }

        $takenOn = null;
        if (!empty($in['taken_on'])) {
            try {
                $takenOn = \Carbon\Carbon::parse($in['taken_on'])->startOfDay();
            } catch (\Throwable $e) {
                throw new MedicalException('La fecha del estudio no es válida.');
            }
            if ($takenOn->isFuture()) {
                throw new MedicalException('La fecha del estudio no puede ser futura.');
            }
        }

        // Los resultados se validan ANTES de guardar el archivo: si alguno es inválido no queda nada a medias.
        $results = [];
        foreach ((array) ($in['results'] ?? []) as $r) {
            if (trim((string) ($r['value'] ?? '')) === '' && empty($r['analyte_name']) && empty($r['analyte_id'])) {
                continue;
            }
            $results[] = $r;
        }
        $stored = PrivateFiles::put($file, $tenantId);

        try {
            return \DB::transaction(function () use ($p, $stored, $kind, $in, $takenOn, $consultationId, $user, $results, $tenantId) {
                $f = MedicalFile::create([
                    'tenant_id' => $tenantId, 'patient_id' => $p->id, 'consultation_id' => $consultationId, 'kind' => $kind,
                    'title' => trim((string) ($in['title'] ?? '')) ?: null, 'original_name' => $stored['original_name'], 'mime' => $stored['mime'],
                    'size' => $stored['size'], 'storage_name' => $stored['storage_name'], 'sha256' => $stored['sha256'],
                    'taken_on' => $takenOn?->toDateString(), 'notes' => trim((string) ($in['notes'] ?? '')) ?: null,
                    'match_status' => 'confirmed', 'uploaded_by' => $user->id,
                ]);

                foreach ($results as $r) {
                    $lab = app(ClinicalItems::class)->addLab($p, [
                        'analyte_id' => $r['analyte_id'] ?? null, 'analyte_name' => $r['analyte_name'] ?? '', 'value' => $r['value'] ?? '',
                        'unit' => $r['unit'] ?? '', 'measured_on' => ($takenOn ?? now())->toDateString(),
                    ], $user);
                    $lab->file_id = $f->id;
                    $lab->consultation_id = $consultationId;
                    $lab->saveQuietly();
                }

                return $f;
            });
        } catch (\Throwable $e) {
            PrivateFiles::forget($tenantId, $stored['storage_name']);
            throw $e;
        }
    }
}
