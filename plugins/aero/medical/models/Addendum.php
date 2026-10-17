<?php namespace Aero\Medical\Models;

use Aero\Medical\Classes\TenantOwned;
use Model;

/** Cambio posterior a una nota firmada. Inmutable y con autor y hora. */
class Addendum extends Model
{
    use TenantOwned;

    public $table = 'aero_medical_addenda';

    public const UPDATED_AT = null;

    public $fillable = ['tenant_id', 'consultation_id', 'user_id', 'user_name', 'body'];

    public $casts = ['body' => 'encrypted'];

    public $belongsTo = ['consultation' => [Consultation::class, 'key' => 'consultation_id']];

    public function beforeUpdate(): void
    {
        throw new \ApplicationException('Las adendas no se pueden modificar.');
    }

    public function beforeDelete(): void
    {
        throw new \ApplicationException('Las adendas no se pueden eliminar.');
    }
}
