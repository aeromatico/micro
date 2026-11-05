<?php namespace Aero\Tickets\Models;

use Model;

/** Comisión por defecto de la plataforma (no son secretos). Cada tipo de entrada puede sobrescribirla. */
class Settings extends Model
{
    public $implement = ['System.Behaviors.SettingsModel'];

    public $settingsCode = 'aero_tickets_settings';
    public $settingsFields = 'fields.yaml';

    public static function defaultFeePercent(): float
    {
        return max(0, min(50, (float) self::get('default_fee_percent', 0)));
    }

    public static function defaultFeeFixed(): float
    {
        return max(0, (float) self::get('default_fee_fixed', 0));
    }

    public static function defaultFeePayer(): string
    {
        return self::get('default_fee_payer', 'buyer') === 'organizer' ? 'organizer' : 'buyer';
    }

    /* ---------- políticas y límites (0 = sin límite) ---------- */

    public static function requireOrganizerApproval(): bool { return (bool) self::get('require_organizer_approval', false); }
    public static function requireEventReview(): bool { return (bool) self::get('require_event_review', false); }
    public static function maxActiveEvents(): int { return max(0, (int) self::get('max_active_events', 0)); }
    public static function maxCapacityPerEvent(): int { return max(0, (int) self::get('max_capacity_per_event', 0)); }
    public static function maxTicketTypesPerEvent(): int { return max(0, (int) self::get('max_ticket_types_per_event', 0)); }
    public static function maxScannersPerTenant(): int { return max(0, (int) self::get('max_scanners_per_tenant', 0)); }
    public static function maxTicketsPerOrder(): int { $v = (int) self::get('max_tickets_per_order', 50); return $v > 0 ? min($v, 500) : 50; }
    public static function defaultReservationMinutes(): int { return max(5, min(1440, (int) self::get('default_reservation_minutes', 30))); }
    public static function reopenLatePayments(): bool { return (bool) self::get('reopen_late_payments', true); }

    /** Cada cambio de parámetros queda en la auditoría. */
    public function afterSave(): void
    {
        $old = (array) json_decode((string) $this->getOriginal('value'), true);
        $new = (array) $this->value;
        $diff = \Aero\Tickets\Classes\Audit::diff($old, $new, array_unique(array_merge(array_keys($old), array_keys($new))));
        if ($diff) {
            \Aero\Tickets\Classes\Audit::record('setting.updated', 'setting', null, ['changes' => $diff, 'note' => 'Parámetros de la plataforma']);
            \Aero\Tickets\Classes\CommissionResolver::flush();
        }
    }
}
