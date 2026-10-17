<?php namespace Aero\Medical\Classes;

/**
 * Qué signos vitales existen en cada modo, con unidad. Son datos: la hoja de
 * seguimiento y el formulario los recorren, no los conocen uno a uno.
 * Los límites son de captura (evitar un 3600 por 36.0), NO rangos clínicos;
 * los rangos "alto/bajo" los define el negocio (fase 3).
 */
class VitalsSchema
{
    public const MUCOUS = [
        'rosy' => 'Rosadas', 'pale' => 'Pálidas', 'icteric' => 'Ictéricas', 'cyanotic' => 'Cianóticas', 'congested' => 'Congestivas',
    ];

    protected const FIELDS = [
        'systolic'        => ['label' => 'PA sistólica', 'unit' => 'mmHg', 'step' => 1, 'min' => 0, 'max' => 400],
        'diastolic'       => ['label' => 'PA diastólica', 'unit' => 'mmHg', 'step' => 1, 'min' => 0, 'max' => 300],
        'heart_rate'      => ['label' => 'Frecuencia cardíaca', 'unit' => 'lpm', 'step' => 1, 'min' => 0, 'max' => 500],
        'resp_rate'       => ['label' => 'Frecuencia respiratoria', 'unit' => 'rpm', 'step' => 1, 'min' => 0, 'max' => 300],
        'temperature_c'   => ['label' => 'Temperatura', 'unit' => '°C', 'step' => 0.1, 'min' => 0, 'max' => 60],
        'spo2'            => ['label' => 'Saturación de oxígeno', 'unit' => '%', 'step' => 1, 'min' => 0, 'max' => 100],
        'weight_kg'       => ['label' => 'Peso', 'unit' => 'kg', 'step' => 0.01, 'min' => 0, 'max' => 9999],
        'height_cm'       => ['label' => 'Talla', 'unit' => 'cm', 'step' => 0.1, 'min' => 0, 'max' => 300],
        'bmi'             => ['label' => 'IMC', 'unit' => 'kg/m²', 'computed' => true],
        'mucous'          => ['label' => 'Mucosas', 'unit' => '', 'options' => self::MUCOUS],
        'crt_seconds'     => ['label' => 'TLLC', 'unit' => 's', 'step' => 0.1, 'min' => 0, 'max' => 20],
        'body_condition'  => ['label' => 'Condición corporal', 'unit' => '/9', 'step' => 1, 'min' => 1, 'max' => 9],
    ];

    protected const BY_MODE = [
        Mode::HUMAN  => ['systolic', 'diastolic', 'heart_rate', 'resp_rate', 'temperature_c', 'spo2', 'weight_kg', 'height_cm', 'bmi'],
        Mode::ANIMAL => ['temperature_c', 'heart_rate', 'resp_rate', 'weight_kg', 'mucous', 'crt_seconds', 'body_condition'],
    ];

    /** @return array<string,array> nombre => definición, en el orden del modo */
    public static function fields(?string $mode): array
    {
        $mode = Mode::valid($mode) ? $mode : Mode::HUMAN;

        return collect(self::BY_MODE[$mode])->mapWithKeys(fn ($k) => [$k => self::FIELDS[$k]])->all();
    }

    /** Campos que el usuario puede escribir (todo menos los calculados). */
    public static function editable(?string $mode): array
    {
        return array_filter(self::fields($mode), fn ($f) => empty($f['computed']));
    }

    public static function bmi(?float $weightKg, ?float $heightCm): ?float
    {
        if (!$weightKg || !$heightCm || $weightKg <= 0 || $heightCm <= 0) {
            return null;
        }

        $m = $heightCm / 100;

        return round($weightKg / ($m * $m), 1);
    }

    /** Texto corto de un valor con su unidad (impresión, hoja). */
    public static function format(string $name, $value): string
    {
        $def = self::FIELDS[$name] ?? null;
        if ($value === null || $value === '' || !$def) {
            return '';
        }
        if (isset($def['options'])) {
            return $def['options'][$value] ?? (string) $value;
        }

        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.') . ($def['unit'] ? ' ' . $def['unit'] : '');
    }
}
