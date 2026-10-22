<?php namespace Aero\Realestate\Classes;

/**
 * Para los controladores del panel: el tenant solo ve y toca lo suyo. Falla
 * cerrado — un usuario sin tenant resoluble no ve nada en vez de verlo todo.
 */
trait ScopesToTenant
{
    protected function scopeToTenant($query)
    {
        if (CurrentTenant::isAdmin()) {
            return $query;
        }

        $id = CurrentTenant::id();

        return $id ? $query->where($query->getModel()->getTable() . '.tenant_id', $id) : $query->whereRaw('1 = 0');
    }

    public function listExtendQuery($query): void
    {
        $this->scopeToTenant($query);
    }

    public function formExtendQuery($query): void
    {
        $this->scopeToTenant($query);
    }

    /** Lo que crea un tenant es suyo; el superadmin elige el tenant en el formulario. */
    public function formBeforeCreate($model): void
    {
        if (!CurrentTenant::isAdmin()) {
            $model->tenant_id = CurrentTenant::id();
        }
    }
}
