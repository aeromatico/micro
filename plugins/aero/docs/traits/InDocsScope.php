<?php namespace Aero\Docs\Traits;

use Aero\Docs\Classes\DocsScope;

/**
 * Categorías y artículos: tenant_id NULL = plataforma. El ámbito NO es un
 * global scope a propósito: el árbol anidado de categorías reordena lft/rgt de
 * toda la tabla y necesita verla completa.
 */
trait InDocsScope
{
    public function scopeInScope($query, ?int $tenantId)
    {
        return $tenantId
            ? $query->where($this->getTable() . '.tenant_id', $tenantId)
            : $query->whereNull($this->getTable() . '.tenant_id');
    }

    /**
     * Ámbito de lectura pública: lo propio del tenant MÁS lo marcado como
     * global (uso general). En la plataforma (null) se ve lo de la plataforma,
     * que ya incluye lo global. A diferencia de inScope(), este scope es solo
     * para mostrar; editar/validar sigue usando inScope().
     */
    public function scopeVisibleIn($query, ?int $tenantId)
    {
        if ($tenantId) {
            return $query->where(fn ($q) => $q
                ->where($this->getTable() . '.tenant_id', $tenantId)
                ->orWhere($this->getTable() . '.is_global', true));
        }

        return $query->whereNull($this->getTable() . '.tenant_id');
    }

    /** Ámbito del sitio o usuario actual. */
    public function scopeInCurrentScope($query)
    {
        return $query->inScope(DocsScope::currentTenantId());
    }

    public static function bootInDocsScope(): void
    {
        static::creating(function ($model) {
            if ($model->tenant_id === null || $model->tenant_id === '') {
                $model->tenant_id = DocsScope::currentTenantId();
            }
        });
    }
}
