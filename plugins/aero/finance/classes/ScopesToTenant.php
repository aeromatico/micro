<?php namespace Aero\Finance\Classes;

/**
 * Para los controladores del panel: el tenant solo ve y toca lo suyo. Falla
 * cerrado — un usuario sin tenant resoluble no ve nada en vez de verlo todo.
 */
trait ScopesToTenant
{
    /**
     * Cada negocio lleva su propio libro y el del portal es el del tenant master:
     * nadie, ni el superadmin, ve libros mezclados. Se trabaja siempre sobre el
     * negocio actual (panel/subdominio/selector de sitios).
     */
    protected function scopeToTenant($query)
    {
        $id = CurrentTenant::id();

        return $id ? $query->where('tenant_id', $id) : $query->whereRaw('1 = 0');
    }

    /** El superadmin ve de qué libro se trata (portal o un negocio) para no confundirlos. */
    protected function announceBooks(): void
    {
        static $done = false;
        if ($done || !CurrentTenant::isAdmin() || request()->ajax()) {
            return;
        }
        $done = true;

        \Flash::info(CurrentTenant::booksLabel());
    }

    public function listExtendQuery($query): void
    {
        $this->announceBooks();
        $this->scopeToTenant($query);
    }

    public function formExtendQuery($query): void
    {
        $this->scopeToTenant($query);
    }
}
