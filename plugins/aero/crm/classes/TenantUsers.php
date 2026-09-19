<?php namespace Aero\Crm\Classes;

use Aero\Sites\Models\Tenant;
use Aero\Sites\Models\TenantUser;
use Backend\Models\User;

/**
 * Usuarios de backend que pueden ser "Responsable" en el CRM: solo los del
 * tenant (su admin primario más los asignados vía TenantUser), nunca los de
 * otros tenants. Un superadmin sin tenant resuelto sigue viendo a todos.
 */
class TenantUsers
{
    /**
     * Mismo criterio que Aero\Sites\Traits\ResolvesCurrentTenant, para código
     * que no es un controlador (modelos, callbacks de opciones).
     */
    public static function currentTenantId(): ?int
    {
        $site = \System\Classes\SiteManager::instance()->getEditSite();
        if ($site?->id && ($id = Tenant::where('site_id', $site->id)->value('id'))) {
            return (int) $id;
        }

        $user = \BackendAuth::getUser();
        if (!$user) {
            return null;
        }

        $id = Tenant::where('backend_user_id', $user->id)->value('id')
            ?? TenantUser::where('user_id', $user->id)->value('tenant_id');

        return $id ? (int) $id : null;
    }

    /**
     * [id => nombre] de los usuarios del tenant. `$keepUserId` (responsable(s)
     * ya guardado(s)) se conserva aunque ya no pertenezca al tenant, para que
     * abrir y guardar un registro viejo no lo borre en silencio.
     */
    public static function options(?int $tenantId, int|array|null $keepUserId = null): array
    {
        $query = User::query()->orderBy('first_name');

        if ($tenantId) {
            $ids = TenantUser::where('tenant_id', $tenantId)->pluck('user_id')
                ->push(Tenant::where('id', $tenantId)->value('backend_user_id'))
                ->merge((array) $keepUserId)
                ->filter()->unique()->values();

            $query->whereIn('id', $ids);
        }
        elseif (!\BackendAuth::getUser()?->is_superuser) {
            // Sin tenant y sin ser superadmin: nada que ofrecer.
            $query->whereIn('id', array_filter((array) $keepUserId));
        }

        return $query->get()
            ->mapWithKeys(fn ($u) => [$u->id => trim($u->first_name . ' ' . $u->last_name) ?: $u->login])
            ->all();
    }
}
