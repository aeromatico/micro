<?php namespace Aero\Docs\Classes;

use Aero\Sites\Models\Tenant;

/**
 * A qué ámbito pertenece lo que se está viendo o editando: un tenant, o la
 * plataforma (null = sitio principal). En el backend sale del sitio de edición
 * o del usuario; en el sitio público, del dominio que atiende la petición.
 */
class DocsScope
{
    public static function currentTenantId(): ?int
    {
        if (!class_exists(Tenant::class)) {
            return null;
        }

        return \App::runningInBackend() ? static::backendTenantId() : static::frontendTenantId();
    }

    protected static function backendTenantId(): ?int
    {
        $site = \System\Classes\SiteManager::instance()->getEditSite();
        if ($site?->id && ($id = Tenant::where('site_id', $site->id)->value('id'))) {
            return (int) $id;
        }

        $user = \BackendAuth::getUser();
        $id = $user ? Tenant::resolveForBackendUser($user)?->id : null;

        return $id ? (int) $id : null;
    }

    protected static function frontendTenantId(): ?int
    {
        $site = \System\Classes\SiteManager::instance()->getActiveSite();
        $id = $site?->id ? Tenant::where('site_id', $site->id)->value('id') : null;

        return $id ? (int) $id : null;
    }
}
