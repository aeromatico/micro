<?php namespace Aero\Medical\Models;

use Aero\Medical\Classes\TenantOwned;
use Model;

/** Qué paciente (p. ej. cuál animal del tutor) atiende una reserva de Office. Office no se modifica. */
class BookingPatient extends Model
{
    use TenantOwned;

    public $table = 'aero_medical_booking_patients';

    public $fillable = ['tenant_id', 'booking_id', 'patient_id'];

    public $belongsTo = ['patient' => [Patient::class, 'key' => 'patient_id']];
}
