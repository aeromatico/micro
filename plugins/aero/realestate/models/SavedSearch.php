<?php namespace Aero\Realestate\Models;

use Model;

/** Criterios guardados por un visitante: cuando entra una propiedad que los cumple se le avisa. */
class SavedSearch extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \Aero\Realestate\Classes\TenantOwned;

    public $table = 'aero_realestate_saved_searches';

    public $fillable = ['tenant_id', 'user_id', 'name', 'criteria', 'email', 'phone', 'is_active'];

    public $jsonable = ['criteria'];

    public $rules = [
        'name'  => 'required|string|max:120',
        'email' => 'nullable|email|max:255',
    ];

    public $dates = ['last_alert_at'];

    public function beforeCreate(): void
    {
        $this->token = \Str::random(40);
        $this->last_alert_at = now(); // solo avisa lo que se publique desde ahora
    }

    public function setPhoneAttribute($value): void
    {
        $this->attributes['phone'] = $value === null || $value === '' ? null : preg_replace('/[^\d+]/', '', (string) $value);
    }
}
