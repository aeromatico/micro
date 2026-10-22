<?php namespace Aero\Realestate\Models;

use Model;

/** Una fila por tenant: duración de visitas, recordatorios y seguimiento comercial. */
class RealestateSettings extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\Nullable;
    use \Aero\Realestate\Classes\TenantOwned;

    public $table = 'aero_realestate_settings';

    public $nullable = ['business_name', 'portal_title', 'portal_description', 'whatsapp', 'phone', 'email', 'address'];

    public $jsonable = ['visit_days'];

    public $fillable = ['tenant_id', 'business_name', 'visit_minutes', 'reminder_hours', 'notify_clients', 'notify_agents', 'notify_admins', 'followup_days',
        'public_enabled', 'portal_title', 'portal_description', 'whatsapp', 'phone', 'email', 'address', 'visit_start_time', 'visit_end_time',
        'visit_days', 'visit_notice_hours', 'visit_advance_days'];

    public $rules = [
        'visit_minutes'  => 'integer|min:10|max:480',
        'reminder_hours' => 'integer|min:0|max:168',
        'followup_days'  => 'integer|min:1|max:90',
        'email'          => 'nullable|email|max:255',
        'visit_start_time' => ['nullable', 'regex:/^([01]\\d|2[0-3]):[0-5]\\d$/'],
        'visit_end_time'   => ['nullable', 'regex:/^([01]\\d|2[0-3]):[0-5]\\d$/'],
    ];

    public static function forTenant(?int $tenantId): self
    {
        return static::firstOrNew(['tenant_id' => $tenantId]) ?: new self();
    }

    public function getVisitMinutesAttribute($v): int
    {
        return (int) ($v ?? 45);
    }

    public function getReminderHoursAttribute($v): int
    {
        return (int) ($v ?? 24);
    }

    public function getFollowupDaysAttribute($v): int
    {
        return (int) ($v ?? 3);
    }

    public function getNotifyClientsAttribute($v): bool
    {
        return (bool) ($v ?? true);
    }

    public function getNotifyAdminsAttribute($v): bool
    {
        return (bool) ($v ?? true);
    }

    public function getNotifyAgentsAttribute($v): bool
    {
        return (bool) ($v ?? true);
    }

    public static function isPublic(?int $tenantId): bool
    {
        return $tenantId && static::where('tenant_id', $tenantId)->where('public_enabled', true)->exists();
    }

    public function getVisitDaysListAttribute(): array
    {
        $days = $this->visit_days;

        return array_map('intval', is_array($days) && $days ? $days : [1, 2, 3, 4, 5, 6]);
    }

    public function getVisitDaysOptions(): array
    {
        return [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
    }
}
