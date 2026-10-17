<?php namespace Aero\Medical\Models;

use Aero\Medical\Classes\Audit;
use Aero\Medical\Classes\CurrentTenant;
use Aero\Medical\Classes\CustomerLink;
use Aero\Medical\Classes\Mode;
use Aero\Medical\Classes\TenantOwned;
use Model;

/**
 * Paciente de un negocio. En humano es la persona; en animal es el animal y el
 * tutor es el Customer de Aero.Office. `kind` guarda el modo con que se creó,
 * así cambiar el modo del negocio no reinterpreta los datos existentes.
 */
class Patient extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\Purgeable;
    use \October\Rain\Database\Traits\SoftDelete;
    use \October\Rain\Database\Traits\Nullable;
    use TenantOwned;

    public $table = 'aero_medical_patients';

    public $fillable = [
        'tenant_id', 'customer_id', 'name', 'document', 'birth_date', 'approx_age', 'sex', 'blood_type',
        'species', 'breed', 'neutered', 'color_marks', 'microchip', 'weight_kg',
        'allergies', 'medications', 'personal_history', 'family_history', 'clinical_notes', 'is_active',
    ];

    /** Contenido clínico: cifrado en reposo. */
    public $casts = [
        'allergies'        => 'encrypted',
        'medications'      => 'encrypted',
        'personal_history' => 'encrypted',
        'family_history'   => 'encrypted',
        'clinical_notes'   => 'encrypted',
        'neutered'         => 'boolean',
        'is_active'        => 'boolean',
        'birth_date'       => 'date',
    ];

    /** Contacto para crear/reutilizar el Customer de Office; no son columnas. */
    public $purgeable = ['contact_name', 'contact_phone', 'contact_email'];

    public $nullable = ['document', 'approx_age', 'sex', 'blood_type', 'species', 'breed', 'color_marks', 'microchip', 'weight_kg', 'birth_date'];

    public $rules = [
        'name'       => 'required|string|max:255',
        'sex'        => 'nullable|in:male,female,unknown',
        'weight_kg'  => 'nullable|numeric|min:0|max:9999',
        'birth_date' => 'nullable|date|before_or_equal:today',
    ];

    public $belongsTo = [
        'customer' => [\Aero\Office\Models\Customer::class, 'key' => 'customer_id'],
    ];

    /** Campos que solo ve personal con acceso clínico (recepción no). */
    public const CLINICAL = ['blood_type', 'allergies', 'medications', 'personal_history', 'family_history', 'clinical_notes', 'weight_kg'];

    public function scopeActive($q)
    {
        return $q->where($this->getTable() . '.is_active', true);
    }

    public function getSexOptions(): array
    {
        return ['male' => $this->kind === Mode::ANIMAL ? 'Macho' : 'Masculino', 'female' => $this->kind === Mode::ANIMAL ? 'Hembra' : 'Femenino', 'unknown' => 'No indicado'];
    }

    public function getSpeciesOptions(): array
    {
        return Mode::species();
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

        return $this->approx_age ?: null;
    }

    public function beforeValidate(): void
    {
        if (!$this->exists || !$this->kind) {
            $this->kind = Mode::forTenant($this->tenant_id) ?? Mode::HUMAN;
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

        if ($this->kind === Mode::ANIMAL && CustomerLink::available() && !$this->customer_id) {
            throw new \ApplicationException('Indique el tutor: elija un cliente o escriba su nombre y teléfono o correo.');
        }
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
