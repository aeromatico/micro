<?php namespace Aero\Medical\Classes\Api;

class Scopes
{
    public const PATIENTS_READ = 'medical.patients.read';

    public static function all(): array
    {
        return [
            self::PATIENTS_READ => 'Consultar pacientes (solo datos administrativos, sin contenido clínico)',
        ];
    }
}
