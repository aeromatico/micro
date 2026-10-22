<?php namespace Aero\Realestate\Models;

use Model;

/** Agente inmobiliario del tenant. El usuario de RainLab.User (user_id) es opcional y se crea desde el formulario. */
class Agent extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;
    use \Aero\Realestate\Classes\TenantOwned;

    public $table = 'aero_realestate_agents';

    public $fillable = ['tenant_id', 'user_id', 'name', 'email', 'phone', 'document', 'notes', 'whatsapp', 'license', 'bio', 'is_active'];

    public $rules = [
        'name'  => 'required|string|max:255',
        'email' => 'nullable|email|max:255',
    ];

    public $attachOne = ['photo' => \System\Models\File::class];

    public function setPhoneAttribute($value): void
    {
        $this->attributes['phone'] = $value === null || $value === '' ? null : preg_replace('/[^\d+]/', '', (string) $value);
    }
}
