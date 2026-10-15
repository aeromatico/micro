<?php namespace Aero\School\Classes;

/**
 * Plan de estudios base del Sistema Educativo Plurinacional (Ley 070):
 * áreas de saberes y conocimientos agrupadas por campo. Es solo un punto de
 * partida: cada colegio las edita después en su curso.
 */
class Curriculum
{
    public const FIELDS = [
        'Comunidad y Sociedad'              => 'Comunidad y Sociedad',
        'Vida Tierra Territorio'            => 'Vida Tierra Territorio',
        'Ciencia, Tecnología y Producción'  => 'Ciencia, Tecnología y Producción',
        'Cosmos y Pensamiento'              => 'Cosmos y Pensamiento',
    ];

    /** @return array<int, array{0:string,1:string}> [área, campo] */
    public static function areas(string $level): array
    {
        $cs = 'Comunidad y Sociedad';
        $vt = 'Vida Tierra Territorio';
        $ct = 'Ciencia, Tecnología y Producción';
        $cp = 'Cosmos y Pensamiento';

        return match ($level) {
            'inicial' => [
                ['Comunidad y Sociedad', $cs], ['Vida Tierra Territorio', $vt],
                ['Ciencia, Tecnología y Producción', $ct], ['Cosmos y Pensamiento', $cp],
            ],
            'secundaria' => [
                ['Comunicación y Lenguajes: Lengua Castellana', $cs], ['Lengua Originaria', $cs], ['Lengua Extranjera', $cs],
                ['Ciencias Sociales', $cs], ['Artes Plásticas y Visuales', $cs], ['Educación Musical', $cs],
                ['Educación Física, Deportes y Recreación', $cs], ['Biología - Geografía', $vt], ['Física', $ct],
                ['Química', $ct], ['Matemática', $ct], ['Técnica Tecnológica General', $ct],
                ['Cosmovisiones, Filosofía y Psicología', $cp], ['Valores, Espiritualidades y Religiones', $cp],
            ],
            default => [
                ['Comunicación y Lenguajes: Lengua Castellana', $cs], ['Lengua Originaria', $cs], ['Lengua Extranjera', $cs],
                ['Ciencias Sociales', $cs], ['Artes Plásticas y Visuales', $cs], ['Educación Musical', $cs],
                ['Educación Física, Deportes y Recreación', $cs], ['Ciencias Naturales', $vt], ['Matemática', $ct],
                ['Técnica Tecnológica General', $ct], ['Cosmovisiones, Filosofía y Psicología', $cp],
                ['Valores, Espiritualidades y Religiones', $cp],
            ],
        };
    }

    /** Crea las áreas base del nivel en el curso (sin duplicar las que ya existen). */
    public static function seedCourse(\Aero\School\Models\Course $course): int
    {
        $n = 0;
        $existing = $course->subjects()->pluck('name')->map(fn ($s) => mb_strtolower($s))->all();
        foreach (self::areas($course->level) as $i => [$name, $field]) {
            if (in_array(mb_strtolower($name), $existing, true)) {
                continue;
            }
            \Aero\School\Models\Subject::create([
                'tenant_id' => $course->tenant_id, 'course_id' => $course->id, 'name' => $name, 'field' => $field, 'sort_order' => $i,
            ]);
            $n++;
        }

        return $n;
    }
}
