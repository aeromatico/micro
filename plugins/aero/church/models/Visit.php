<?php namespace Aero\Church\Models;

use Model;

/** Persona que visita por primera vez. Se le da seguimiento y, al integrarse, se convierte en Member. */
class Visit extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \Aero\Church\Classes\TenantOwned;

    public const STATUSES = ['new' => 'Nuevo', 'contacted' => 'Contactado', 'invited' => 'Invitado', 'integrated' => 'Integrado', 'closed' => 'Sin continuidad'];

    public $table = 'aero_church_visits';

    public static $audited = true;
    public static $auditLabel = 'visitante';
    public static $auditType = 'visit';

    public $attributes = ['status' => 'new', 'source' => 'manual', 'consent_messages' => false, 'consent_data' => false];

    public $fillable = [
        'tenant_id', 'branch_id', 'responsible_id', 'interested_group_id', 'name', 'phone', 'email', 'how_heard', 'interest',
        'consent_messages', 'consent_data', 'visit_date', 'status', 'source', 'next_action_date', 'notes',
    ];

    public $nullable = ['branch_id', 'responsible_id', 'interested_group_id', 'next_action_date'];

    public $rules = [
        'name'       => 'required|string|max:255',
        'email'      => 'nullable|email|max:255',
        'visit_date' => 'required|date',
        'status'     => 'required|in:new,contacted,invited,integrated,closed',
    ];

    public $dates = ['visit_date', 'next_action_date'];

    public $belongsTo = [
        'branch'      => [Branch::class, 'key' => 'branch_id'],
        'member'      => [Member::class, 'key' => 'member_id'],
        'responsible' => [Member::class, 'key' => 'responsible_id'],
        'group'       => [Group::class, 'key' => 'interested_group_id'],
    ];

    public $hasMany = [
        'contacts' => [VisitContact::class, 'key' => 'visit_id'],
    ];

    public function beforeValidate(): void
    {
        $this->assertReferencesVisible(['branch_id' => Branch::class, 'responsible_id' => Member::class, 'interested_group_id' => Group::class]);
    }

    public function setPhoneAttribute($value): void
    {
        $this->attributes['phone'] = $value === null || $value === '' ? null : preg_replace('/[^\d+]/', '', (string) $value);
    }

    public function getStatusOptions(): array
    {
        return self::STATUSES;
    }

    public function getBranchIdOptions(): array
    {
        return Branch::visible()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function getResponsibleIdOptions(): array
    {
        return Member::visible()->whereIn('status', ['active', 'new'])->orderBy('first_name')->get()->pluck('full_name', 'id')->all();
    }

    public function getInterestedGroupIdOptions(): array
    {
        return Group::visible()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all();
    }

    /** Sin responsable, o con la próxima acción vencida. */
    public function scopeNeedsAttention($query)
    {
        return $query->whereIn('status', ['new', 'contacted', 'invited'])
            ->where(fn ($q) => $q->whereNull('responsible_id')->orWhere('next_action_date', '<=', now()->toDateString()));
    }
}
