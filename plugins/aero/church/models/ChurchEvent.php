<?php namespace Aero\Church\Models;

use Model;

/** Evento del calendario (culto, reunión, retiro, conferencia). Privado por tenant. */
class ChurchEvent extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \Aero\Church\Classes\TenantOwned;

    public const TYPES = [
        'service' => 'Culto', 'meeting' => 'Reunión', 'retreat' => 'Retiro', 'conference' => 'Conferencia', 'special' => 'Actividad especial',
    ];

    public $table = 'aero_church_events';

    public static $audited = true;
    public static $auditLabel = 'evento';
    public static $auditType = 'event';

    public $attributes = ['type' => 'service', 'status' => 'scheduled', 'registration_open' => true, 'is_public' => false];

    public $fillable = [
        'tenant_id', 'branch_id', 'group_id', 'responsible_id', 'title', 'type', 'description', 'starts_at', 'ends_at',
        'location', 'capacity', 'registration_open', 'reminder_hours', 'status', 'is_public',
    ];

    public $rules = [
        'title'     => 'required|string|max:255',
        'type'      => 'required|in:service,meeting,retreat,conference,special',
        'starts_at' => 'required|date',
        'ends_at'   => 'nullable|date|after_or_equal:starts_at',
        'capacity'  => 'nullable|integer|min:1',
        'status'    => 'required|in:scheduled,cancelled',
    ];

    public $dates = ['starts_at', 'ends_at'];

    public $belongsTo = [
        'branch'      => [Branch::class, 'key' => 'branch_id'],
        'group'       => [Group::class, 'key' => 'group_id'],
        'responsible' => [Member::class, 'key' => 'responsible_id'],
    ];

    public $hasMany = [
        'registrations' => [EventRegistration::class, 'key' => 'event_id'],
    ];

    public function beforeValidate(): void
    {
        $this->assertReferencesVisible([
            'branch_id' => Branch::class, 'group_id' => Group::class, 'responsible_id' => Member::class,
        ]);
    }

    /** Avisa (si la iglesia lo activó) a los inscritos cuando el evento cambia de horario, lugar o se cancela. */
    public function afterUpdate(): void
    {
        $changed = array_values(array_intersect(['starts_at', 'ends_at', 'location', 'status'], array_keys($this->getChanges())));
        if ($changed) {
            \Event::fire('aero.church.eventChanged', [$this, $changed]);
        }
    }

    public function getTypeOptions(): array
    {
        return self::TYPES;
    }

    public function getStatusOptions(): array
    {
        return ['scheduled' => 'Programado', 'cancelled' => 'Cancelado'];
    }

    public function getBranchIdOptions(): array
    {
        return Branch::visible()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function getGroupIdOptions(): array
    {
        return Group::managed()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function getResponsibleIdOptions(): array
    {
        return Member::visible()->orderBy('first_name')->get()->pluck('full_name', 'id')->all();
    }

    public function scopeUpcoming($query)
    {
        return $query->where('status', 'scheduled')->where('starts_at', '>=', now());
    }

    /** Inscritos con cupo (sin contar lista de espera ni cancelados). */
    public function getSeatedAttribute(): int
    {
        return $this->registrations()->where('status', 'registered')->count();
    }

    public function getAttendedAttribute(): int
    {
        return $this->registrations()->where('status', 'registered')->whereNotNull('checked_in_at')->count();
    }

    public function getIsFullAttribute(): bool
    {
        return $this->capacity !== null && $this->seated >= $this->capacity;
    }

    public function isOpenForRegistration(): bool
    {
        $end = $this->ends_at ?: $this->starts_at;

        return $this->status === 'scheduled' && $this->registration_open && !$end->isPast();
    }
}
