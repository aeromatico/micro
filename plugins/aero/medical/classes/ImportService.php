<?php namespace Aero\Medical\Classes;

use Aero\Medical\Models\ImportBatch;
use Aero\Medical\Models\MedicalFile;
use Aero\Medical\Models\Patient;
use Illuminate\Http\UploadedFile;

/**
 * Carga masiva de historias anteriores. Cada archivo se guarda cifrado y CONSERVA el original; la
 * asociación con un paciente se sugiere (nombre y documento en el nombre del archivo y, en Word y texto,
 * dentro del contenido) pero SIEMPRE la confirma una persona: hasta entonces el archivo no está en ninguna ficha.
 */
class ImportService
{
    public const MAX_FILES = 40;

    /** @param UploadedFile[] $files */
    public function receive(int $tenantId, array $files, $user, ?string $label = null): array
    {
        if (!$files) {
            throw new MedicalException('Elija al menos un archivo.');
        }
        if (count($files) > self::MAX_FILES) {
            throw new MedicalException('Máximo ' . self::MAX_FILES . ' archivos por lote.');
        }

        $batch = ImportBatch::create(['tenant_id' => $tenantId, 'user_id' => $user->id, 'label' => $label ? mb_substr(trim($label), 0, 255) : null, 'total' => 0]);
        $patients = Patient::where('tenant_id', $tenantId)->get(['id', 'name', 'document']);
        $ok = 0;
        $errors = [];

        foreach ($files as $file) {
            try {
                $stored = PrivateFiles::put($file, $tenantId);
            } catch (MedicalException $e) {
                $errors[] = $file->getClientOriginalName() . ': ' . $e->getMessage();
                continue;
            }

            [$suggested, $reason] = $this->suggest($patients, $stored['original_name'], $this->text($file->getRealPath(), $stored['mime']));
            try {
                MedicalFile::create([
                    'tenant_id' => $tenantId, 'patient_id' => null, 'kind' => 'history', 'original_name' => $stored['original_name'], 'mime' => $stored['mime'],
                    'size' => $stored['size'], 'storage_name' => $stored['storage_name'], 'sha256' => $stored['sha256'], 'import_id' => $batch->id,
                    'match_status' => $suggested ? 'suggested' : 'pending', 'suggested_patient_id' => $suggested, 'suggestion_reason' => $reason, 'uploaded_by' => $user->id,
                ]);
                $ok++;
            } catch (\Throwable $e) {
                PrivateFiles::forget($tenantId, $stored['storage_name']);
                $errors[] = $file->getClientOriginalName() . ': no se pudo guardar.';
                \Log::error('Aero.Medical: importación de archivo fallida: ' . $e->getMessage());
            }
        }

        $batch->total = $ok;
        $batch->save();
        Audit::record('import', $tenantId, null, 'import', $batch->id);

        return ['batch' => $batch, 'imported' => $ok, 'errors' => $errors];
    }

    public function confirm(MedicalFile $f, Patient $p, $user): MedicalFile
    {
        if ((int) $f->tenant_id !== (int) $p->tenant_id) {
            throw new MedicalException('El paciente elegido no es de este negocio.');
        }
        if (!$f->import_id || !in_array($f->match_status, ['pending', 'suggested'], true)) {
            throw new MedicalException('Este archivo ya fue asociado o descartado.');
        }

        $f->patient_id = $p->id;
        $f->match_status = 'confirmed';
        $f->save();
        Audit::record('update', $f->tenant_id, $p->id, 'file', $f->id, ['patient_id']);

        return $f;
    }

    public function discard(MedicalFile $f, $user): void
    {
        if (!$f->import_id || !in_array($f->match_status, ['pending', 'suggested'], true)) {
            throw new MedicalException('Solo se descartan archivos que aún esperan revisión.');
        }
        $f->match_status = 'discarded';
        $f->save();
        $f->delete();
    }

    /**
     * @param \Illuminate\Support\Collection $patients
     * @return array{0:?int,1:?string} paciente sugerido y por qué
     */
    public function suggest($patients, string $filename, string $text = ''): array
    {
        $base = $this->norm(pathinfo($filename, PATHINFO_FILENAME));
        $haystack = $base . ' ' . $this->norm($text);
        // El documento debe aparecer COMPLETO como palabra (no como parte de otro número).
        $allWords = array_flip(preg_split('/\s+/', preg_replace('/(?<=\d)[\s.\-](?=\d)/', '', $haystack) . ' ' . $haystack));

        $best = [0, null, null];
        $tie = false;
        foreach ($patients as $p) {
            $score = 0;
            $why = null;

            $doc = strtolower(preg_replace('/[^A-Za-z0-9]+/', '', (string) $p->document));
            $docMatch = mb_strlen($doc) >= 5 && preg_match('/\d/', $doc) && isset($allWords[$doc]);
            if ($docMatch) {
                $score = 100;
                $why = 'coincide el documento';
            } else {
                $tokens = array_values(array_filter(explode(' ', $this->norm($p->name)), fn ($t) => mb_strlen($t) >= 3));
                if ($tokens) {
                    $words = array_flip(explode(' ', $haystack));
                    $hit = count(array_filter($tokens, fn ($t) => isset($words[$t])));
                    if ($hit === count($tokens) && count($tokens) >= 2) {
                        $score = 80;
                        $why = 'coincide el nombre completo';
                    } elseif ($hit >= 2) {
                        $score = 60;
                        $why = "coincide el nombre ({$hit} de " . count($tokens) . ' palabras)';
                    } elseif ($hit === 1 && count($tokens) === 1) {
                        $score = 50;
                        $why = 'coincide el nombre (una palabra)';
                    }
                }
            }

            if ($score > $best[0]) {
                $best = [$score, $p->id, $why];
                $tie = false;
            } elseif ($score > 0 && $score === $best[0]) {
                $tie = true;
            }
        }

        // Empate entre pacientes distintos: no se adivina.
        if ($best[0] < 50 || $tie) {
            return [null, $tie ? 'varios pacientes coinciden: elija' : null];
        }

        return [$best[1], $best[2]];
    }

    protected function norm(string $s): string
    {
        $s = mb_strtolower($s);
        $s = strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);

        return trim(preg_replace('/[^a-z0-9]+/', ' ', $s));
    }

    /** Texto buscable de Word (.docx) y texto plano; PDF e imágenes solo se asocian por el nombre. */
    protected function text(string $path, string $mime): string
    {
        try {
            if ($mime === 'text/plain') {
                return mb_substr((string) file_get_contents($path, false, null, 0, 20000), 0, 5000);
            }
            if ($mime === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
                $zip = new \ZipArchive();
                if ($zip->open($path) === true) {
                    $xml = (string) $zip->getFromName('word/document.xml', 2 * 1024 * 1024);
                    $zip->close();

                    return mb_substr(html_entity_decode(strip_tags(str_replace(['</w:p>', '<w:tab/>'], ' ', $xml))), 0, 5000);
                }
            }
        } catch (\Throwable $e) {
            // sin texto: se asocia por el nombre del archivo
        }

        return '';
    }
}
