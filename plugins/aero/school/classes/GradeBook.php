<?php namespace Aero\School\Classes;

use Aero\School\Models\Course;
use Aero\School\Models\Grade;
use Aero\School\Models\Settings;
use Aero\School\Models\Student;
use Aero\School\Models\Subject;
use Aero\School\Models\Year;
use Event;

/** Captura de notas y cálculo de promedios (escala 0-100, aprobación configurable, 51 por defecto). */
class GradeBook
{
    public const PERIOD_NAMES = [1 => '1er Trimestre', 2 => '2do Trimestre', 3 => '3er Trimestre', 4 => '4to Periodo', 5 => '5to Periodo', 6 => '6to Periodo'];

    public static function periodsFor(Course $course): int
    {
        return max(1, (int) ($course->year?->periods ?: 3));
    }

    /** Guarda las notas de un área y periodo para todo el curso. $rows = [student_id => ['ser'=>..,'saber'=>..,'qualitative'=>..]]. */
    public function save(Course $course, Subject $subject, int $period, array $rows): int
    {
        if ($subject->course_id !== $course->id || $subject->tenant_id !== $course->tenant_id) {
            throw new SchoolException('El área no pertenece a este curso.');
        }
        if ($period < 1 || $period > self::periodsFor($course)) {
            throw new SchoolException('Periodo no válido.');
        }

        $valid = Student::where('course_id', $course->id)->where('tenant_id', $course->tenant_id)->pluck('id')->all();
        $saved = 0;

        foreach ($rows as $studentId => $row) {
            $studentId = (int) $studentId;
            if (!in_array($studentId, $valid, true)) {
                continue;
            }

            $grade = Grade::firstOrNew(['student_id' => $studentId, 'subject_id' => $subject->id, 'period' => $period]);
            $grade->tenant_id = $course->tenant_id;
            $grade->course_id = $course->id;
            $grade->year_id = $course->year_id;

            foreach (Grade::MAX as $dim => $max) {
                $raw = isset($row[$dim]) ? str_replace(',', '.', trim((string) $row[$dim])) : '';
                if ($raw === '') {
                    $grade->{$dim} = null;
                    continue;
                }
                if (!is_numeric($raw) || (float) $raw < 0 || (float) $raw > $max) {
                    throw new SchoolException("Nota no válida en «{$dim}»: debe estar entre 0 y {$max}.");
                }
                $grade->{$dim} = round((float) $raw, 2);
            }

            if ($course->isQualitative()) {
                $grade->qualitative = trim((string) ($row['qualitative'] ?? '')) ?: null;
                $grade->total = null;
            } else {
                $grade->recalculate();
            }

            $grade->save();
            $saved++;
        }

        Event::fire('aero.school.gradesSaved', [$course, $subject, $period, $saved]);

        return $saved;
    }

    /**
     * Datos de boletín de un alumno: áreas con nota por periodo, promedio anual y resultado.
     *
     * @return array<string,mixed>
     */
    public function report(Student $student): array
    {
        $course = $student->course()->with('year')->first();
        if (!$course) {
            throw new SchoolException('El estudiante no tiene curso asignado.');
        }

        $settings = Settings::forTenant($student->tenant_id);
        $passing = (int) ($settings->passing_mark ?: 51);
        $periods = self::periodsFor($course);
        $qualitative = $course->isQualitative();

        $grades = Grade::where('student_id', $student->id)->get()->groupBy('subject_id');
        $rows = [];
        $failed = 0;

        foreach ($course->subjects()->orderBy('sort_order')->orderBy('id')->get() as $subject) {
            $byPeriod = ($grades[$subject->id] ?? collect())->keyBy('period');
            $notes = [];
            $quals = [];
            for ($p = 1; $p <= $periods; $p++) {
                $notes[$p] = $byPeriod[$p]->total ?? null;
                $quals[$p] = $byPeriod[$p]->qualitative ?? null;
            }
            $filled = array_filter($notes, fn ($n) => $n !== null);
            $annual = $filled ? (int) round(array_sum($filled) / count($filled)) : null;
            $approved = $annual === null ? null : $annual >= $passing;
            if (!$qualitative && $annual !== null && !$approved) {
                $failed++;
            }

            $rows[] = [
                'subject' => $subject->name, 'field' => $subject->field, 'notes' => $notes, 'qualitative' => $quals,
                'annual' => $annual, 'approved' => $approved,
            ];
        }

        $annuals = array_filter(array_column($rows, 'annual'), fn ($n) => $n !== null);
        $complete = $rows && count($annuals) === count($rows)
            && collect($rows)->every(fn ($r) => count(array_filter($r['notes'], fn ($n) => $n !== null)) === $periods);

        return [
            'student'     => $student,
            'course'      => $course,
            'year'        => $course->year ?: Year::currentFor($student->tenant_id),
            'settings'    => $settings,
            'periods'     => $periods,
            'qualitative' => $qualitative,
            'passing'     => $passing,
            'rows'        => $rows,
            'average'     => $annuals ? (int) round(array_sum($annuals) / count($annuals)) : null,
            'failed'      => $failed,
            'complete'    => $complete,
            'result'      => $qualitative ? null : ($complete ? ($failed ? "Reprobado en {$failed} área(s)" : 'Aprobado') : 'En curso'),
            'attendance'  => AttendanceService::summary($student),
        ];
    }

    /** Ranking simple del curso por promedio (para el docente; no se muestra al padre). */
    public function courseAverages(Course $course): array
    {
        return Grade::where('course_id', $course->id)->whereNotNull('total')
            ->selectRaw('student_id, ROUND(AVG(total)) as avg')->groupBy('student_id')->pluck('avg', 'student_id')->all();
    }
}
