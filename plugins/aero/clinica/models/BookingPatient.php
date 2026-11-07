<?php namespace Aero\Clinica\Models;

use Aero\Clinica\Classes\TenantOwned;
use Model;

/** Qué paciente (p. ej. cuál familiar del cliente) atiende una reserva de Office. Office no se modifica. */
class BookingPatient extends Model
{
    use TenantOwned;

    public $table = 'aero_clinica_booking_patients';

    public $fillable = ['tenant_id', 'booking_id', 'patient_id'];

    public $belongsTo = ['patient' => [Patient::class, 'key' => 'patient_id']];
}
