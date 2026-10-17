<?php namespace Aero\Medical\Models;

use Aero\Medical\Classes\Audit;
use Aero\Medical\Classes\Mode;
use Aero\Medical\Classes\TenantOwned;
use Model;

/** Una fila por tenant. Sin secretos aquí (SettingsModel/columnas no cifradas). */
class ClinicSettings extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\Purgeable;
    use TenantOwned;

    public $table = 'aero_medical_settings';

    public $fillable = ['tenant_id', 'mode', 'enabled', 'consultation_template_id', 'control_template_id'];

    /** Casilla del formulario, no es columna. */
    public $purgeable = ['confirm_mode_change'];

    public $rules = [
        'mode' => 'nullable|in:human,animal',
    ];

    public static function forTenant(?int $tenantId): self
    {
        return static::firstOrNew(['tenant_id' => $tenantId]);
    }

    public function getConsultationTemplateIdOptions(): array
    {
        return ['' => '— Plantilla por defecto —'] + NoteTemplate::templateOptions($this->tenant_id ?: \Aero\Medical\Classes\CurrentTenant::id(), 'consultation');
    }

    public function getControlTemplateIdOptions(): array
    {
        return ['' => '— Plantilla por defecto —'] + NoteTemplate::templateOptions($this->tenant_id ?: \Aero\Medical\Classes\CurrentTenant::id(), 'control');
    }

    public function getModeOptions(): array
    {
        return Mode::modes();
    }

    /** Pacientes ya cargados con el modo actual: cambiarlo no los borra pero pide confirmación. */
    public function patientCount(): int
    {
        return $this->tenant_id ? Patient::withTrashed()->where('tenant_id', $this->tenant_id)->count() : 0;
    }

    public function beforeSave(): void
    {
        $original = $this->getOriginal('mode');

        if ($this->exists && $original && $this->isDirty('mode') && ($n = $this->patientCount()) > 0 && !$this->confirm_mode_change) {
            throw new \ApplicationException(
                "Ya hay {$n} paciente(s) cargados. Cambiar el modo no borra nada: solo cambia los textos y campos para los nuevos. "
                . 'Marque «Confirmo el cambio de modo» para continuar.'
            );
        }
    }

    public function afterSave(): void
    {
        if ($this->wasRecentlyCreated || $this->wasChanged('mode')) {
            Audit::record('settings', $this->tenant_id, null, 'settings', $this->id, ['mode']);
        }
    }
}
