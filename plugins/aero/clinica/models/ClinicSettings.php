<?php namespace Aero\Clinica\Models;

use Aero\Clinica\Classes\Audit;
use Aero\Clinica\Classes\CurrentTenant;
use Aero\Clinica\Classes\TenantOwned;
use Model;

/** Una fila por tenant. Sin secretos aquí (SettingsModel/columnas no cifradas). */
class ClinicSettings extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use TenantOwned;

    public $table = 'aero_clinica_settings';

    public $fillable = ['tenant_id', 'enabled', 'consultation_template_id', 'control_template_id', 'vaccine_reminders', 'reminder_days_before'];

    public $casts = ['vaccine_reminders' => 'boolean'];

    public $rules = [
        'reminder_days_before' => 'nullable|integer|min:1|max:60',
    ];

    public static function forTenant(?int $tenantId): self
    {
        return static::firstOrNew(['tenant_id' => $tenantId]);
    }

    public function getConsultationTemplateIdOptions(): array
    {
        return ['' => '— Plantilla por defecto —'] + NoteTemplate::templateOptions($this->tenant_id ?: CurrentTenant::id(), 'consultation');
    }

    public function getControlTemplateIdOptions(): array
    {
        return ['' => '— Plantilla por defecto —'] + NoteTemplate::templateOptions($this->tenant_id ?: CurrentTenant::id(), 'control');
    }

    public function afterSave(): void
    {
        if ($this->wasRecentlyCreated) {
            Audit::record('settings', $this->tenant_id, null, 'settings', $this->id, ['created']);
        }
    }
}
