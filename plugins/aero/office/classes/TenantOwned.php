<?php namespace Aero\Office\Classes;

/**
 * Para los modelos con tenant_id: scope `visible`/`forTenant` y, al crear,
 * hereda el tenant del panel si quien crea no es superadmin y el campo viene vacío.
 */
trait TenantOwned
{
    public static function bootTenantOwned(): void
    {
        // Los formularios mandan '' en los campos vacíos; MySQL estricto lo rechaza en columnas numéricas/fecha.
        static::saving(function ($model) {
            foreach ($model->getAttributes() as $key => $value) {
                if ($value === '' && preg_match('/(_id|_minutes|_hours|_days|_at|_on)$|^(price|lat|lng|capacity)$/', $key)) {
                    $model->setAttribute($key, null);
                }
            }
        });

        static::creating(function ($model) {
            if (empty($model->tenant_id) && !CurrentTenant::isAdmin() && \BackendAuth::getUser()) {
                $model->tenant_id = CurrentTenant::id();
            }
        });
    }

    /** Lo que el usuario del panel puede ver: todo (superadmin) o solo su tenant (falla cerrado). */
    public function scopeVisible($query)
    {
        return CurrentTenant::isAdmin() ? $query : $this->scopeForTenant($query, CurrentTenant::id());
    }

    public function scopeForTenant($query, ?int $tenantId)
    {
        return $tenantId ? $query->where($this->getTable() . '.tenant_id', $tenantId) : $query->whereRaw('1 = 0');
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
            return; // servicio (portal público, jobs): quien llama ya fijó el tenant
        }

        foreach ($map as $attr => $class) {
            if (!empty($this->{$attr}) && !$class::visible()->whereKey($this->{$attr})->exists()) {
                throw new \ApplicationException('La referencia «' . $attr . '» no es válida.');
            }
        }
    }

    /** Quita de las relaciones belongsToMany lo que sea de otro tenant (ids manipulados). */
    protected function pruneForeignPivots(array $relations): void
    {
        foreach ($relations as $name => $class) {
            $table = (new $class)->getTable();
            $foreign = $this->{$name}()->where($table . '.tenant_id', '!=', $this->tenant_id)->pluck($table . '.id')->all();
            if ($foreign) {
                $this->{$name}()->detach($foreign);
            }
        }
    }
}
