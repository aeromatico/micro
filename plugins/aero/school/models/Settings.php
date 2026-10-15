<?php namespace Aero\School\Models;

use Aero\School\Classes\CurrentTenant;
use Model;

/** Una fila por tenant (no es SettingsModel: tiene tenant_id). */
class Settings extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\Nullable;

    public $table = 'aero_school_settings';

    public $nullable = ['bank_account_id', 'reminder_template', 'overdue_template', 'absence_template', 'school_name', 'sie_code', 'logo_url'];

    public $fillable = [
        'tenant_id', 'enabled', 'school_name', 'sie_code', 'district', 'city', 'director', 'logo_url', 'currency', 'bank_account_id',
        'passing_mark', 'reminders_enabled', 'reminder_days', 'overdue_every_days', 'overdue_max', 'late_fee', 'notify_absence',
        'notify_late', 'reminder_template', 'overdue_template', 'absence_template', 'portal_grades', 'portal_block_if_debt',
    ];

    public const DEFAULTS = [
        'enabled' => true, 'currency' => 'BOB', 'passing_mark' => 51, 'reminders_enabled' => true, 'reminder_days' => '3,0',
        'overdue_every_days' => 7, 'overdue_max' => 4, 'notify_absence' => true, 'notify_late' => false,
        'portal_grades' => true, 'portal_block_if_debt' => false,
    ];

    public $rules = [
        'reminder_days' => ['regex:/^\s*\d+(\s*,\s*\d+)*\s*$/'],
        'passing_mark'  => 'integer|min:1|max:100',
    ];

    public function beforeSave(): void
    {
        foreach ($this->nullable as $f) {
            if (array_key_exists($f, $this->attributes) && trim((string) $this->attributes[$f]) === '') {
                $this->attributes[$f] = null;
            }
        }
    }

    public static function forTenant(?int $tenantId): self
    {
        $row = static::firstOrNew(['tenant_id' => $tenantId]);
        if (!$row->exists) {
            // Sin fila guardada: los mismos valores por defecto de la tabla.
            $row->forceFill(self::DEFAULTS);
        }

        return $row;
    }

    public static function isEnabled(?int $tenantId): bool
    {
        return (bool) (static::where('tenant_id', $tenantId)->value('enabled') ?? true);
    }

    public function reminderDays(): array
    {
        return array_values(array_unique(array_map('intval', array_filter(explode(',', (string) $this->reminder_days), 'strlen'))));
    }

    public function getBankAccountIdOptions(): array
    {
        if (!class_exists(\Aero\Pay\Models\BankAccount::class)) {
            return ['' => '— Sin cuenta (cobro manual) —'];
        }

        return ['' => '— Sin cuenta (cobro manual) —'] + \Aero\Pay\Models\BankAccount::active()
            ->where('tenant_id', (CurrentTenant::isAdmin() ? $this->tenant_id : CurrentTenant::id()) ?: 0)
            ->get()->pluck('label', 'id')->all();
    }
}
