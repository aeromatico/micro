<?php namespace Aero\Church\Models;

use Model;

/** Un contacto hecho a un visitante (llamada, WhatsApp, visita) con su próxima acción. */
class VisitContact extends Model
{
    use \Aero\Church\Classes\TenantOwned;

    public const CHANNELS = ['call' => 'Llamada', 'whatsapp' => 'WhatsApp', 'visit' => 'Visita', 'other' => 'Otro'];

    public $table = 'aero_church_visit_contacts';

    public $attributes = ['channel' => 'whatsapp'];

    public $fillable = ['tenant_id', 'visit_id', 'channel', 'note', 'next_action', 'next_action_date'];

    public $dates = ['next_action_date'];

    public $belongsTo = [
        'visit' => [Visit::class, 'key' => 'visit_id'],
    ];

    public function beforeCreate(): void
    {
        $this->recorded_by = \BackendAuth::getUser()?->id;
    }

    /** Un contacto nuevo deja la fecha de la próxima acción en el visitante y lo pasa de «nuevo» a «contactado». */
    public function afterCreate(): void
    {
        $v = Visit::find($this->visit_id);
        if (!$v) {
            return;
        }
        $v->next_action_date = $this->next_action_date;
        if ($v->status === 'new') {
            $v->status = 'contacted';
        }
        $v->save();
    }
}
