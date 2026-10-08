<?php namespace Aero\Gym\Classes;

/**
 * Para los modelos con tenant_id: scope `forTenant` y, al crear, hereda el
 * tenant del panel si quien crea no es superadmin y el campo viene vacío.
 */
trait TenantOwned
{
    public static function bootTenantOwned(): void
    {
        // Los formularios mandan '' en los campos vacíos; en columnas numéricas/fecha opcionales
        // MySQL estricto lo rechaza (1366). Aquí se convierte a NULL antes de guardar.
        static::saving(function ($model) {
            foreach ($model->getAttributes() as $key => $value) {
                if ($value === '' && preg_match('/(_id|_cm|_kg|_pct|_minutes)$|^(birthdate|capacity|classes_per_week|starts_on|ends_on)$/', $key)) {
                    $model->setAttribute($key, null);
                }
            }
        });

        static::creating(function ($model) {
            if (empty($model->tenant_id) && !CurrentTenant::isAdmin()) {
                $model->tenant_id = CurrentTenant::id();
            }
        });
    }

    /** Lo que el usuario del panel puede ver: todo (superadmin) o solo su tenant (falla cerrado). */
    public function scopeVisible($query)
    {
        return CurrentTenant::isAdmin() ? $query : $this->scopeForTenant($query, CurrentTenant::id());
    }

    public function getTenantIdOptions(): array
    {
        if (!class_exists(\Aero\Sites\Models\Tenant::class)) {
            return [];
        }

        return \Aero\Sites\Models\Tenant::orderBy('name')->pluck('name', 'id')->all();
    }

    /** Un tenant no puede apuntar a registros de otro (id manipulado en el POST). */
    protected function assertReferencesVisible(array $map): void
    {
        if (!\BackendAuth::getUser()) {
            return; // código de servicio (jobs, API): quien llama ya fijó el tenant
        }

        foreach ($map as $attr => $class) {
            if (!empty($this->{$attr}) && !$class::visible()->whereKey($this->{$attr})->exists()) {
                throw new \ApplicationException('La referencia «' . $attr . '» no es válida.');
            }
        }
    }

    public function scopeForTenant($query, ?int $tenantId)
    {
        return $tenantId ? $query->where($this->getTable() . '.tenant_id', $tenantId) : $query->whereRaw('1 = 0');
    }
}
