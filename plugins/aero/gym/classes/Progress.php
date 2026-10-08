<?php namespace Aero\Gym\Classes;

use Aero\Gym\Models\Measurement;
use Illuminate\Support\Collection;

/** Series, variaciones y gráfico (SVG puro, sin librerías) del progreso corporal de un socio. */
class Progress
{
    /** Campos de medición con su etiqueta y unidad. */
    public const FIELDS = [
        'weight_kg' => ['Peso', 'kg'], 'body_fat_pct' => ['Grasa corporal', '%'], 'muscle_pct' => ['Músculo', '%'],
        'waist_cm' => ['Cintura', 'cm'], 'chest_cm' => ['Pecho', 'cm'], 'hip_cm' => ['Cadera', 'cm'],
        'arm_cm' => ['Brazo', 'cm'], 'thigh_cm' => ['Muslo', 'cm'],
    ];

    /** Mediciones ordenadas de la más antigua a la más reciente. */
    public static function history(int $memberId): Collection
    {
        return Measurement::where('member_id', $memberId)->orderBy('measured_on')->orderBy('id')->get();
    }

    /** Primera vs. última medición: variación de cada campo con datos en ambas. */
    public static function changes(Collection $rows): array
    {
        if ($rows->count() < 2) {
            return [];
        }
        $first = $rows->first();
        $last = $rows->last();
        $out = [];

        foreach (self::FIELDS as $f => [$label, $unit]) {
            if ($first->{$f} === null || $last->{$f} === null) {
                continue;
            }
            $out[$f] = ['label' => $label, 'unit' => $unit, 'from' => (float) $first->{$f}, 'to' => (float) $last->{$f}, 'delta' => round((float) $last->{$f} - (float) $first->{$f}, 1)];
        }
        if ($first->bmi !== null && $last->bmi !== null) {
            $out['bmi'] = ['label' => 'IMC', 'unit' => '', 'from' => $first->bmi, 'to' => $last->bmi, 'delta' => round($last->bmi - $first->bmi, 1)];
        }

        return $out;
    }

    /**
     * Gráfico de línea en SVG. $points = [['2026-10-01', 80.5], …]. Escala
     * ajustada al rango (no parte de cero) para que se vea el cambio.
     */
    public static function chart(array $points, string $color = '#6366f1', string $unit = ''): string
    {
        $points = array_values(array_filter($points, fn ($p) => $p[1] !== null));
        $n = count($points);
        if ($n === 0) {
            return '';
        }

        [$w, $h, $pad] = [320, 120, 14];
        $vals = array_column($points, 1);
        $min = min($vals);
        $max = max($vals);
        $span = ($max - $min) ?: 1;
        $min -= $span * 0.15;
        $max += $span * 0.15;
        $span = $max - $min;

        $xy = [];
        foreach ($points as $i => [$d, $v]) {
            $x = $n === 1 ? $w / 2 : $pad + ($w - 2 * $pad) * $i / ($n - 1);
            $y = $h - $pad - ($h - 2 * $pad) * (($v - $min) / $span);
            $xy[] = [round($x, 1), round($y, 1), $d, $v];
        }

        $line = implode(' ', array_map(fn ($p) => "{$p[0]},{$p[1]}", $xy));
        $dots = '';
        foreach ($xy as [$x, $y, $d, $v]) {
            $dots .= '<circle cx="' . $x . '" cy="' . $y . '" r="3.5" fill="' . $color . '"><title>' . e($d . ': ' . $v . ' ' . $unit) . '</title></circle>';
        }

        return '<svg viewBox="0 0 ' . $w . ' ' . $h . '" class="w-full h-auto" role="img" aria-label="Evolución">'
            . ($n > 1 ? '<polyline points="' . $line . '" fill="none" stroke="' . $color . '" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"/>' : '')
            . $dots . '</svg>';
    }
}
