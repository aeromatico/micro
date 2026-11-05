<?php namespace Aero\Tickets\Models;

use Aero\Tickets\Classes\OrganizerService;
use Model;

/** Solicitud y estado de un organizador (espacio de trabajo que vende entradas). Se mueve solo con OrganizerService. */
class Organizer extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_tickets_organizers';
    public $fillable = ['legal_name', 'document', 'contact_name', 'contact_email', 'contact_phone', 'city', 'description'];
    public $dates = ['requested_at', 'decided_at'];

    public $rules = [
        'legal_name' => 'nullable|string|max:255', 'document' => 'nullable|string|max:40',
        'contact_email' => 'nullable|email|max:255', 'contact_phone' => 'nullable|string|max:30',
    ];

    public static array $statuses = ['pending' => 'Pendiente de aprobación', 'approved' => 'Aprobado', 'rejected' => 'Rechazado', 'suspended' => 'Suspendido'];

    public function getStatusOptions(): array { return self::$statuses; }
    public function getStatusLabelAttribute(): string { return self::$statuses[$this->status] ?? $this->status; }

    public function getTenantNameAttribute(): ?string
    {
        return class_exists(\Aero\Sites\Models\Tenant::class) ? \Aero\Sites\Models\Tenant::find($this->tenant_id)?->name : null;
    }

    public function events()
    {
        return Event::where('tenant_id', $this->tenant_id);
    }

    /** El estado solo cambia desde OrganizerService (deja historial y avisa). */
    public function beforeSave(): void
    {
        if (!OrganizerService::$writing && $this->exists && $this->isDirty(['status', 'decided_at', 'decided_by', 'decision_note'])) {
            throw new \ApplicationException('El estado del organizador solo cambia desde la administración.');
        }
    }

    public function beforeDelete(): void
    {
        throw new \ApplicationException('Los organizadores no se eliminan; se suspenden.');
    }

    public function scopeForTenant($q, ?int $tenantId) { return $tenantId ? $q->where($this->getTable() . '.tenant_id', $tenantId) : $q->whereRaw('1 = 0'); }
}
