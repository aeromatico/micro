<?php namespace Aero\Office\Models;

use Model;

/** Una fila por tenant: identidad del negocio y reglas generales de reserva. */
class OfficeSettings extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\Nullable;
    use \Aero\Office\Classes\TenantOwned;

    public $table = 'aero_office_settings';

    public $nullable = ['lat', 'lng', 'business_name', 'industry', 'description', 'logo_url', 'phone', 'email', 'address', 'brand_color', 'booking_terms'];

    public $fillable = [
        'tenant_id', 'business_name', 'industry', 'description', 'logo_url', 'phone', 'email', 'address', 'lat', 'lng',
        'brand_color', 'currency', 'enabled', 'public_enabled', 'min_notice_hours', 'max_advance_days', 'slot_step_minutes',
        'cancel_window_hours', 'auto_assign_worker', 'notify_customers', 'reminder_hours', 'booking_terms',
    ];

    public $rules = [
        'email'             => 'nullable|email|max:255',
        'brand_color'       => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        'slot_step_minutes' => 'integer|min:5|max:240',
        'max_advance_days'  => 'integer|min:1|max:730',
    ];

    public static function forTenant(?int $tenantId): self
    {
        return static::firstOrNew(['tenant_id' => $tenantId]);
    }

    /** Sin fila de configuración el módulo está activo pero sin portal público. */
    public static function isEnabled(?int $tenantId): bool
    {
        return (bool) (static::where('tenant_id', $tenantId)->value('enabled') ?? true);
    }

    public static function isPublic(?int $tenantId): bool
    {
        return $tenantId && static::where('tenant_id', $tenantId)->where('enabled', true)->where('public_enabled', true)->exists();
    }

    public function getSlotStepMinutesOptions(): array
    {
        return [5 => '5 minutos', 10 => '10 minutos', 15 => '15 minutos', 20 => '20 minutos', 30 => '30 minutos', 60 => '1 hora'];
    }
}
