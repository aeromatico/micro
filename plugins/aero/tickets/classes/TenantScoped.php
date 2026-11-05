<?php namespace Aero\Tickets\Classes;

/** scope visible/forTenant para modelos con tenant_id propio (sin evento padre). */
trait TenantScoped
{
    public function scopeVisible($q)
    {
        return CurrentTenant::isAdmin() ? $q : $this->scopeForTenant($q, CurrentTenant::id());
    }

    public function scopeForTenant($q, ?int $tenantId)
    {
        return $tenantId ? $q->where($this->getTable() . '.tenant_id', $tenantId) : $q->whereRaw('1 = 0');
    }
}
