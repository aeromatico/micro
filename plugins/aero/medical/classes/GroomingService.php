<?php namespace Aero\Medical\Classes;

use Aero\Medical\Models\GroomingVisit;
use Aero\Medical\Models\MedicalFile;
use Aero\Medical\Models\Patient;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;

/** Visitas de peluquería y spa: servicio, fecha, notas, fotos antes/después (cifradas) y próxima cita. */
class GroomingService
{
    use AnimalOnly;

    public const MAX_PHOTOS_PER_SIDE = 10;
    public const PHOTO_MIMES = ['image/png', 'image/jpeg', 'image/webp'];

    /** @param array{service?:string,visited_on?:string,notes?:string,next_visit_on?:string} $in */
    public function create(Patient $p, array $in, $user): GroomingVisit
    {
        $this->assertAnimal($p);

        $v = new GroomingVisit(['tenant_id' => $p->tenant_id, 'patient_id' => $p->id, 'performed_by' => $user->id]);
        $this->fill($v, $in);
        $v->save();

        return $v;
    }

    public function update(GroomingVisit $v, array $in): GroomingVisit
    {
        $this->fill($v, $in);
        $v->save();

        return $v;
    }

    /** @param 'before'|'after' $side */
    public function addPhoto(GroomingVisit $v, string $side, UploadedFile $file, $user): MedicalFile
    {
        if (!in_array($side, ['before', 'after'], true)) {
            throw new MedicalException('Indique si la foto es de antes o de después.');
        }
        if (MedicalFile::where('grooming_id', $v->id)->where('kind', 'grooming_' . $side)->count() >= self::MAX_PHOTOS_PER_SIDE) {
            throw new MedicalException('Máximo ' . self::MAX_PHOTOS_PER_SIDE . ' fotos de ' . ($side === 'before' ? 'antes' : 'después') . ' por visita.');
        }

        $stored = PrivateFiles::put($file, (int) $v->tenant_id);
        if (!in_array($stored['mime'], self::PHOTO_MIMES, true)) {
            PrivateFiles::forget((int) $v->tenant_id, $stored['storage_name']);
            throw new MedicalException('Las fotos deben ser imágenes PNG, JPG o WebP.');
        }

        try {
            return MedicalFile::create([
                'tenant_id' => $v->tenant_id, 'patient_id' => $v->patient_id, 'kind' => 'grooming_' . $side, 'grooming_id' => $v->id,
                'original_name' => $stored['original_name'], 'mime' => $stored['mime'], 'size' => $stored['size'], 'storage_name' => $stored['storage_name'],
                'sha256' => $stored['sha256'], 'taken_on' => $v->visited_on->toDateString(), 'match_status' => 'confirmed', 'uploaded_by' => $user->id,
            ]);
        } catch (\Throwable $e) {
            PrivateFiles::forget((int) $v->tenant_id, $stored['storage_name']); // sin registro no queda el archivo cifrado huérfano
            throw $e;
        }
    }

    public function removePhoto(GroomingVisit $v, int $fileId): void
    {
        $f = MedicalFile::where('grooming_id', $v->id)->find($fileId);
        if (!$f) {
            throw new MedicalException('Foto no encontrada en esta visita.');
        }
        $f->delete();
    }

    /** Elimina la visita y sus fotos (quedan eliminadas de forma lógica y auditadas). */
    public function delete(GroomingVisit $v): void
    {
        MedicalFile::where('grooming_id', $v->id)->get()->each->delete();
        $v->delete();
    }

    /** Agenda la próxima cita en Office (BookingService) y la liga a la visita. */
    public function bookNext(GroomingVisit $v, array $in, $user): \Aero\Office\Models\Booking
    {
        $b = Scheduling::bookControl($v->patient, [
            'branch_id' => $in['branch_id'] ?? null, 'service_id' => $in['service_id'] ?? null, 'worker_id' => $in['worker_id'] ?? null,
            'starts_at' => $in['starts_at'] ?? null, 'reason' => $in['reason'] ?? ('Peluquería y spa: ' . $v->service),
        ], $user);

        $v->booking_id = $b->id;
        $v->next_visit_on = $b->starts_at->toDateString();
        $v->save();

        return $b;
    }

    protected function fill(GroomingVisit $v, array $in): void
    {
        $service = trim((string) ($in['service'] ?? ''));
        if ($service === '') {
            throw new MedicalException('Indique el servicio (baño, corte, spa…).');
        }

        $visited = $this->date($in['visited_on'] ?? null, 'La fecha') ?? now()->startOfDay();
        if ($visited->isFuture()) {
            throw new MedicalException('La fecha de la visita no puede ser futura.');
        }
        $next = $this->date($in['next_visit_on'] ?? null, 'La próxima cita');
        if ($next && $next->lt($visited)) {
            throw new MedicalException('La próxima cita no puede ser anterior a la visita.');
        }

        $v->service = mb_substr($service, 0, 255);
        $v->visited_on = $visited->toDateString();
        $v->next_visit_on = $next?->toDateString();
        $notes = trim((string) ($in['notes'] ?? ''));
        $v->notes = $notes !== '' ? mb_substr($notes, 0, 2000) : null;
    }

    protected function date($v, string $label): ?Carbon
    {
        if ($v === null || trim((string) $v) === '') {
            return null;
        }
        try {
            return Carbon::parse($v)->startOfDay();
        } catch (\Throwable $e) {
            throw new MedicalException($label . ' no es una fecha válida.');
        }
    }
}
