<?php namespace Aero\Invoicing\Classes;

/**
 * Para los controladores del panel: el tenant solo ve y toca lo suyo. Falla
 * cerrado: sin tenant resoluble no ve nada en vez de verlo todo.
 */
trait ScopesToTenant
{
    protected function scopeToTenant($query)
    {
        if (CurrentTenant::isAdmin()) {
            return $query;
        }

        return $query->forTenant(CurrentTenant::id());
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
