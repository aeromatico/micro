<?php namespace Aero\Medical\Classes;

use Aero\Medical\Models\ClinicSettings;

/**
 * Humano o animal como DATOS: vocabulario y campos visibles por modo en un
 * solo lugar. Nada más del plugin pregunta "¿es animal?" con un if suelto:
 * consulta term(), fieldsFor() o species().
 */
class Mode
{
    public const HUMAN = 'human';
    public const ANIMAL = 'animal';

    protected const VOCAB = [
        self::HUMAN => [
            'patient'      => 'Paciente',
            'patients'     => 'Pacientes',
            'guardian'     => 'Persona de contacto',
            'professional' => 'Médico',
            'consultation' => 'Consulta',
            'control'      => 'Control',
            'history'      => 'Antecedentes personales',
            'name'         => 'Nombre completo',
        ],
        self::ANIMAL => [
            'patient'      => 'Paciente',
            'patients'     => 'Pacientes',
            'guardian'     => 'Tutor',
            'professional' => 'Veterinario',
            'consultation' => 'Consulta',
            'control'      => 'Control',
            'history'      => 'Antecedentes del animal',
            'name'         => 'Nombre del animal',
        ],
    ];

    /** Campos del paciente que solo existen en cada modo (el resto es común). */
    protected const ONLY = [
        self::HUMAN  => ['blood_type', 'family_history'],
        self::ANIMAL => ['species', 'breed', 'neutered', 'color_marks', 'microchip', 'weight_kg', 'approx_age', 'contact_name'],
    ];

    /** Especies base. Los rangos clínicos por especie los carga el negocio (fase 3). */
    protected const SPECIES = [
        'dog' => 'Perro', 'cat' => 'Gato', 'bird' => 'Ave', 'rabbit' => 'Conejo', 'rodent' => 'Roedor',
        'reptile' => 'Reptil', 'horse' => 'Equino', 'cattle' => 'Bovino', 'sheep_goat' => 'Ovino / caprino',
        'pig' => 'Porcino', 'other' => 'Otra',
    ];

    public static function modes(): array
    {
        return [self::HUMAN => 'Humano (personas)', self::ANIMAL => 'Animal (veterinaria)'];
    }

    public static function valid(?string $mode): bool
    {
        return in_array($mode, [self::HUMAN, self::ANIMAL], true);
    }

    /** Modo elegido por el negocio; null = aún no lo eligió (el módulo pide elegirlo). */
    public static function forTenant(?int $tenantId): ?string
    {
        if (!$tenantId) {
            return null;
        }

        $mode = ClinicSettings::where('tenant_id', $tenantId)->value('mode');

        return static::valid($mode) ? $mode : null;
    }

    public static function term(string $key, ?string $mode): string
    {
        $mode = static::valid($mode) ? $mode : self::HUMAN;

        return static::VOCAB[$mode][$key] ?? $key;
    }

    /** Campos que NO aplican al modo dado (se ocultan en formularios y listas). */
    public static function hiddenFields(?string $mode): array
    {
        $mode = static::valid($mode) ? $mode : self::HUMAN;
        $other = $mode === self::HUMAN ? self::ANIMAL : self::HUMAN;

        return static::ONLY[$other];
    }

    public static function species(): array
    {
        return static::SPECIES;
    }
}
