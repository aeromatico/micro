<?php namespace Aero\Notify\Traits;

use Aero\Notify\Models\Rule;
use Aero\Sites\Traits\ResolvesCurrentTenant;
use BackendAuth;

/**
 * Aísla los listados y formularios del backend al tenant que está operando.
 *
 * Se apoya en ResolvesCurrentTenant de Aero.Sites en vez de reescribir la
 * cascada SiteManager -> backend_user_id -> tenant_users, que ya está copiada a
 * mano en dos sitios del proyecto. Una sola implementación, un solo bug posible.
 */
trait ScopesToTenant
{
    use ResolvesCurrentTenant;

    /**
     * Un superadmin ve todo. El criterio es el mismo que usa Aero.Sites, para
     * no inventar una tercera definición de "superadmin" en el proyecto.
     */
    protected function isSuperadmin(): bool
    {
        $user = BackendAuth::getUser();

        if (!$user) {
            return false;
        }

        // hasAccess() y no hasPermission(): solo el primero tiene en cuenta
        // el flag is_superuser de October.
        return $user->hasAccess(['aero.sites.superadmin']);
    }

    /**
     * Tenant efectivo para escribir: 0 (global) si es superadmin y no está
     * mirando ningún tenant en concreto.
     */
    protected function effectiveTenantId(): int
    {
        return (int) ($this->getCurrentTenantId() ?? Rule::GLOBAL_TENANT);
    }

    public function listExtendQuery($query): void
    {
        $this->applyTenantScope($query);
    }

    public function formExtendQuery($query): void
    {
        $this->applyTenantScope($query);
    }

    /**
     * El tenant ve sus filas y, en solo lectura, las globales de las que
     * hereda; sin ellas la pantalla mentiría sobre lo que se envía.
     */
    protected function applyTenantScope($query): void
    {
        if ($this->isSuperadmin()) {
            return;
        }

        $tenantId = $this->getCurrentTenantId();

        if (!$tenantId) {
            // Un usuario de backend sin tenant resuelto no debe ver nada ajeno.
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereIn('tenant_id', [$tenantId, Rule::GLOBAL_TENANT]);
    }

    /**
     * Marca las filas globales para que el listado las muestre atenuadas y se
     * distingan de las propias del tenant.
     */
    public function listInjectRowClass($record, $definition = null)
    {
        if (isset($record->tenant_id) && (int) $record->tenant_id === Rule::GLOBAL_TENANT && !$this->isSuperadmin()) {
            return 'safe disabled';
        }

        return null;
    }
}
