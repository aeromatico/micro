<?php namespace Aero\Workflows\Classes;

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

        return $id ? $query->where('tenant_id', $id) : $query->whereRaw('1 = 0');
    }

    public function listExtendQuery($query): void
    {
        $this->scopeToTenant($query);
    }

    public function formExtendQuery($query): void
    {
        $this->scopeToTenant($query);
    }
}
