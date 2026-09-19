<?php namespace Aero\Crm\Classes;

/**
 * Opciones del selector "Responsable" (campo owner_id, también el filtro de
 * la lista): solo usuarios del tenant del registro, o del tenant actual si el
 * modelo aún no tiene uno (registro nuevo, filtro de lista).
 */
trait HasTenantOwnerOptions
{
    /**
     * El desplegable con opción vacía envía '' y MySQL rechaza '' en una
     * columna entera (el guardado fallaba): se guarda como NULL.
     */
    public function initializeHasTenantOwnerOptions(): void
    {
        $this->bindEvent('model.beforeSave', function () {
            if ($this->owner_id === '' || $this->owner_id === '0' || $this->owner_id === 0) {
                $this->owner_id = null;
            }
        });
    }

    public function getOwnerIdOptions(): array
    {
        return TenantUsers::options(
            $this->tenant_id ? (int) $this->tenant_id : TenantUsers::currentTenantId(),
            $this->owner_id ? (int) $this->owner_id : null
        );
    }
}
