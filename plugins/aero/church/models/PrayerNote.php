<?php namespace Aero\Church\Models;

use Model;

/** Nota de acompañamiento de una petición. Mismo acceso restringido que la petición. */
class PrayerNote extends Model
{
    use \Aero\Church\Classes\TenantOwned;

    public $table = 'aero_church_prayer_notes';

    public $fillable = ['tenant_id', 'prayer_id', 'note'];

    public $belongsTo = [
        'prayer' => [Prayer::class, 'key' => 'prayer_id'],
    ];

    public function beforeCreate(): void
    {
        $this->author_user_id = \BackendAuth::getUser()?->id;
    }
}
