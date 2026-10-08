<?php namespace Aero\Livechat\Classes;

use Aero\Sites\Models\Tenant;
use Aero\Sites\Models\TenantUser;
use Backend\Models\User;

/**
 * Mismo criterio que Aero\Sites\Traits\ResolvesCurrentTenant, para código que
 * no es un controlador (modelos, callbacks de opciones) — patrón replicado
 * en cada plugin aero/* (ver Aero\Crm\Classes\TenantUsers).
 */
class TenantScope
{
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

        $id = Tenant::resolveForBackendUser($user)?->id;

        return $id ? (int) $id : null;
    }

    /** [id => nombre] de los agentes disponibles para un ámbito (tenant o plataforma). */
    public static function agentOptions(?int $tenantId, int|array|null $keepUserId = null): array
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
            $query->whereIn('id', array_filter((array) $keepUserId));
        }

        return $query->get()
            ->mapWithKeys(fn ($u) => [$u->id => trim($u->first_name . ' ' . $u->last_name) ?: $u->login])
            ->all();
    }
}
