<?php namespace Aero\School\Classes;

use Aero\School\Models\Attendance;
use Aero\School\Models\Course;
use Aero\School\Models\Settings;
use Aero\School\Models\Student;
use Event;

class AttendanceService
{
    public const DEFAULT_ABSENCE = "Estimado(a) {apoderado}: {estudiante} ({curso}) registra {estado} el {fecha}. Si es un error o desea justificar, comuníquese con {colegio}.";

    /**
     * Guarda la asistencia de un curso en una fecha. $rows = [student_id => ['status'=>..., 'note'=>...]].
     * Avisa a los apoderados solo de lo que cambió (una vez por alumno y día).
     *
     * @return array{saved:int, notified:int}
     */
    public function record(Course $course, \DateTimeInterface|string $date, array $rows, ?int $userId = null, bool $notify = true): array
    {
        $date = \Carbon\Carbon::parse($date)->toDateString();
        $valid = Student::where('course_id', $course->id)->where('tenant_id', $course->tenant_id)->pluck('id')->all();
        $settings = Settings::forTenant($course->tenant_id);
        $saved = 0;
        $notified = 0;

        foreach ($rows as $studentId => $row) {
            $studentId = (int) $studentId;
            $status = $row['status'] ?? 'present';
            if (!in_array($studentId, $valid, true) || !isset(Attendance::STATUSES[$status])) {
                continue;
            }

            $att = Attendance::firstOrNew(['student_id' => $studentId, 'date' => $date]);
            $changed = !$att->exists || $att->status !== $status;
            $att->fill([
                'tenant_id' => $course->tenant_id, 'course_id' => $course->id, 'status' => $status,
                'note' => ($row['note'] ?? null) ?: null, 'recorded_by' => $userId,
            ]);
            if ($changed) {
                $att->notified_at = null;
            }
            $att->save();
            $saved++;

            if ($notify && !$att->notified_at && $this->shouldNotify($status, $settings)) {
                if ($this->notifyGuardians($att, $course, $settings)) {
                    $att->notified_at = now();
                    $att->save();
                    $notified++;
                }
            }
        }

        Event::fire('aero.school.attendanceRecorded', [$course, $date, $saved]);

        return ['saved' => $saved, 'notified' => $notified];
    }

    protected function shouldNotify(string $status, Settings $s): bool
    {
        return ($status === 'absent' && ($s->exists ? $s->notify_absence : true)) || ($status === 'late' && $s->exists && $s->notify_late);
    }

    protected function notifyGuardians(Attendance $att, Course $course, Settings $s): bool
    {
        $student = Student::with('guardians')->find($att->student_id);
        if (!$student) {
            return false;
        }

        $ok = false;
        foreach ($student->guardians as $g) {
            $body = Notifier::render($s->absence_template, self::DEFAULT_ABSENCE, [
                'apoderado' => $g->name, 'estudiante' => $student->full_name, 'curso' => $course->name,
                'estado' => $att->status === 'late' ? 'un atraso' : 'una falta', 'fecha' => \Carbon\Carbon::parse($att->date)->format('d/m/Y'),
                'colegio' => Notifier::schoolName($course->tenant_id),
            ]);
            $ok = Notifier::whatsapp($course->tenant_id, $g->phone, $body, $g->name) || $ok;
        }

        return $ok;
    }

    /** Resumen de asistencia de un alumno (y porcentaje) en una gestión o rango. */
    public static function summary(Student $student, ?string $from = null, ?string $to = null): array
    {
        $q = $student->attendances();
        $from && $q->whereDate('date', '>=', $from);
        $to && $q->whereDate('date', '<=', $to);
        $counts = $q->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status')->all();
        $total = array_sum($counts);
        $present = ($counts['present'] ?? 0) + ($counts['late'] ?? 0) + ($counts['excused'] ?? 0);

        return [
            'present' => (int) ($counts['present'] ?? 0), 'absent' => (int) ($counts['absent'] ?? 0),
            'late' => (int) ($counts['late'] ?? 0), 'excused' => (int) ($counts['excused'] ?? 0),
            'total' => (int) $total, 'pct' => $total ? round($present * 100 / $total, 1) : null,
        ];
    }
}
