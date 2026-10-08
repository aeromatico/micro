<?php namespace Aero\Gym\Models;

use Aero\Gym\Classes\CurrentTenant;
use Model;

/** Una fila por tenant (no es SettingsModel: tiene tenant_id). */
class GymSettings extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\Nullable;

    public $table = 'aero_gym_settings';

    /** El formulario manda '' en los vacíos: MySQL estricto lo rechaza en columnas enteras. */
    public $nullable = ['bank_account_id', 'shop_collection_id', 'reminder_template'];

    public $fillable = [
        'tenant_id', 'enabled', 'access_mode', 'qr_rotation_seconds', 'bank_account_id', 'currency', 'reminder_days', 'reminders_enabled', 'grace_days',
        'cancel_window_hours', 'waitlist_auto_promote', 'shop_collection_id', 'reminder_template',
    ];

    public $rules = [
        'reminder_days' => ['regex:/^\s*\d+(\s*,\s*\d+)*\s*$/'],
    ];

    /** El formulario manda '' en los campos vacíos; en columnas enteras/texto opcional debe ser NULL. */
    public function beforeSave(): void
    {
        foreach (['bank_account_id', 'shop_collection_id', 'reminder_template'] as $f) {
            if (array_key_exists($f, $this->attributes) && trim((string) $this->attributes[$f]) === '') {
                $this->attributes[$f] = null;
            }
        }
    }

    public static function forTenant(?int $tenantId): self
    {
        return static::firstOrNew(['tenant_id' => $tenantId]);
    }

    /** Sin fila de configuración el gimnasio está activo. */
    public static function isEnabled(?int $tenantId): bool
    {
        return (bool) (static::where('tenant_id', $tenantId)->value('enabled') ?? true);
    }

    public static function accessMode(?int $tenantId): string
    {
        return static::where('tenant_id', $tenantId)->value('access_mode') ?: 'member_qr';
    }

    public function getAccessModeOptions(): array
    {
        return [
            'member_qr' => 'El socio muestra su QR (el gimnasio lo lee con un lector)',
            'gym_qr'    => 'El gimnasio muestra un QR que cambia; el socio lo escanea con su móvil',
        ];
    }

    public function reminderDays(): array
    {
        return array_values(array_unique(array_map('intval', array_filter(explode(',', (string) $this->reminder_days), 'strlen'))));
    }

    public function getBankAccountIdOptions(): array
    {
        if (!class_exists(\Aero\Pay\Models\BankAccount::class)) {
            return [];
        }

        return ['' => '— Sin cuenta (cobro manual) —'] + \Aero\Pay\Models\BankAccount::active()
            ->when(!CurrentTenant::isAdmin(), fn ($q) => $q->where('tenant_id', CurrentTenant::id() ?: 0))
            ->get()->pluck('name', 'id')->all();
    }

    public function getShopCollectionIdOptions(): array
    {
        if (!class_exists(\Aero\Shop\Models\Collection::class)) {
            return [];
        }

        return ['' => '— Todas —'] + \Aero\Shop\Models\Collection::query()
            ->when(!CurrentTenant::isAdmin(), fn ($q) => $q->where('tenant_id', CurrentTenant::id() ?: 0))
            ->orderBy('name')->pluck('name', 'id')->all();
    }
}
