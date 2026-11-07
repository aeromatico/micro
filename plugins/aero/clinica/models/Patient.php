<?php namespace Aero\Clinica\Models;

use Aero\Clinica\Classes\Audit;
use Aero\Clinica\Classes\CurrentTenant;
use Aero\Clinica\Classes\CustomerLink;
use Aero\Clinica\Classes\TenantOwned;
use Model;

/**
 * Paciente de un consultorio, centro de salud o clínica. Los datos de contacto viven en el Customer de
 * Aero.Office (opcional); la ficha guarda lo demográfico y el contenido clínico cifrado.
 */
class Patient extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\Purgeable;
    use \October\Rain\Database\Traits\SoftDelete;
    use \October\Rain\Database\Traits\Nullable;
    use TenantOwned;

    public $table = 'aero_clinica_patients';

    public $fillable = [
        'tenant_id', 'customer_id', 'name', 'document', 'birth_date', 'sex', 'blood_type',
        'address', 'city', 'occupation', 'marital_status',
        'emergency_name', 'emergency_phone', 'emergency_relation',
        'allergies', 'medications', 'chronic_conditions', 'habits', 'personal_history', 'family_history', 'clinical_notes', 'is_active',
    ];

    /** Contenido clínico: cifrado en reposo. */
    public $casts = [
        'allergies'        => 'encrypted',
        'medications'      => 'encrypted',
        'personal_history' => 'encrypted',
        'family_history'   => 'encrypted',
        'chronic_conditions' => 'encrypted',
        'habits'           => 'encrypted',
        'clinical_notes'   => 'encrypted',
        'is_active'        => 'boolean',
        'birth_date'       => 'date',
    ];

    /** Contacto para crear/reutilizar el Customer de Office; no son columnas. */
    public $purgeable = ['contact_phone', 'contact_email'];

    public $nullable = ['document', 'sex', 'blood_type', 'birth_date', 'address', 'city', 'occupation', 'marital_status', 'emergency_name', 'emergency_phone', 'emergency_relation'];

    public $rules = [
        'name'       => 'required|string|max:255',
        'sex'        => 'nullable|in:male,female,unknown',
        'document'   => 'nullable|string|max:64',
        'blood_type' => 'nullable|string|max:8',
        'marital_status' => 'nullable|in:single,married,common_law,divorced,widowed',
        'emergency_name'  => 'nullable|string|max:255',
        'emergency_phone' => 'nullable|string|max:32',
        'emergency_relation' => 'nullable|string|max:64',
        'birth_date' => 'nullable|date|before_or_equal:today',
    ];

    public $belongsTo = [
        'customer' => [\Aero\Office\Models\Customer::class, 'key' => 'customer_id'],
    ];

    /** Campos que solo ve personal con acceso clínico (recepción no). */
    public const CLINICAL = ['blood_type', 'allergies', 'medications', 'personal_history', 'chronic_conditions', 'habits', 'family_history', 'clinical_notes'];

    public function scopeActive($q)
    {
        return $q->where($this->getTable() . '.is_active', true);
    }

    public function getSexOptions(): array
    {
        return ['male' => 'Masculino', 'female' => 'Femenino', 'unknown' => 'No indicado'];
    }

    public function getMaritalStatusOptions(): array
    {
        return ['single' => 'Soltero/a', 'married' => 'Casado/a', 'common_law' => 'Unión libre', 'divorced' => 'Divorciado/a', 'widowed' => 'Viudo/a'];
    }

    public function getBloodTypeOptions(): array
    {
        return array_combine($t = ['O+', 'O-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-'], $t);
    }

    public function getCustomerIdOptions(): array
    {
        return ['' => '— Sin vincular —'] + CustomerLink::options($this->tenant_id ?: CurrentTenant::id());
    }

    public function getAgeLabelAttribute(): ?string
    {
        if ($this->birth_date) {
            $d = $this->birth_date->diff(now());

            return $d->y >= 1 ? $d->y . ' a' . ($d->m ? ' ' . $d->m . ' m' : '') : max(0, $d->m) . ' m';
        }

        return null;
    }

    public function beforeValidate(): void
    {
        if ($this->is_active === null) {
            $this->is_active = true;
        }

        // Un tenant no puede vincular clientes de otro (id manipulado en el POST).
        if ($this->customer_id && CustomerLink::available() && \BackendAuth::getUser()) {
            if (!array_key_exists($this->customer_id, CustomerLink::options($this->tenant_id))) {
                throw new \ApplicationException('El cliente elegido no pertenece a este negocio.');
            }
        }
    }

    public function beforeSave(): void
    {
        CustomerLink::ensure($this);
    }

    public function afterCreate(): void
    {
        Audit::record('create', $this->tenant_id, $this->id, 'patient', $this->id);
    }

    public function afterUpdate(): void
    {
        Audit::record('update', $this->tenant_id, $this->id, 'patient', $this->id, array_keys($this->getChanges()));
    }

    public function afterDelete(): void
    {
        Audit::record('delete', $this->tenant_id, $this->id, 'patient', $this->id);
    }

    public function afterRestore(): void
    {
        Audit::record('restore', $this->tenant_id, $this->id, 'patient', $this->id);
    }
}
