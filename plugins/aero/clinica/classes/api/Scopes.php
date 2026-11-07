<?php namespace Aero\Clinica\Classes\Api;

class Scopes
{
    public const PATIENTS_READ = 'clinica.patients.read';

    public static function all(): array
    {
        return [
            self::PATIENTS_READ => 'Consultar pacientes (solo datos administrativos, sin contenido clínico)',
        ];
    }
}
