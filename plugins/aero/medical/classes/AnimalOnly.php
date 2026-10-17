<?php namespace Aero\Medical\Classes;

use Aero\Medical\Models\Patient;

/** Desparasitaciones y peluquería existen solo para pacientes animales; el servidor lo exige, no solo el menú. */
trait AnimalOnly
{
    protected function assertAnimal(Patient $p): void
    {
        if ($p->kind !== Mode::ANIMAL) {
            throw new MedicalException('Este módulo es solo para pacientes animales.');
        }
    }
}
