<?php namespace Aero\Realestate\Models;

use Model;

/** Propietario del inmueble. Privado por tenant. El usuario de RainLab.User (user_id) es opcional y se crea desde el formulario. */
class Owner extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;
    use \Aero\Realestate\Classes\TenantOwned;

    public $table = 'aero_realestate_owners';

    public $fillable = ['civil_status', 'nationality', 'tenant_id', 'user_id', 'name', 'email', 'phone', 'document', 'notes', 'address'];

    public $rules = [
        'name'  => 'required|string|max:255',
        'email' => 'nullable|email|max:255',
    ];

    public function setPhoneAttribute($value): void
    {
        $this->attributes['phone'] = $value === null || $value === '' ? null : preg_replace('/[^\d+]/', '', (string) $value);
    }
}
