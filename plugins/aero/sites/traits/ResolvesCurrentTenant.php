<?php namespace Aero\Sites\Traits;

use Aero\Sites\Models\Tenant;
use Aero\Sites\Models\TenantUser;
use BackendAuth;
use System\Classes\SiteManager;

trait ResolvesCurrentTenant
{
    protected ?Tenant $currentTenant = null;

    protected function getCurrentTenant(): ?Tenant
    {
        if ($this->currentTenant) return $this->currentTenant;

        // 1. SiteManager — superadmin usando el selector de sitios del backend
        $site = SiteManager::instance()->getEditSite();
        if ($site?->id) {
            $this->currentTenant = Tenant::where('site_id', $site->id)->first();
        }

        // 2. Fallback — resolver por el backend user autenticado
        if (!$this->currentTenant) {
            $user = BackendAuth::getUser();
            if ($user) {
                // Admin primario del tenant (creado automáticamente en el provisioning)
                $this->currentTenant = Tenant::where('backend_user_id', $user->id)->first();

                // Admin adicional asignado manualmente vía TenantUser
                if (!$this->currentTenant) {
                    $tenantUser = TenantUser::where('user_id', $user->id)->first();
                    if ($tenantUser) {
                        $this->currentTenant = Tenant::find($tenantUser->tenant_id);
                    }
                }
            }
        }

        return $this->currentTenant;
    }

    protected function getCurrentTenantId(): ?int
    {
        return $this->getCurrentTenant()?->id;
    }

    protected function scopeQueryToTenant($query): mixed
    {
        $tenant = $this->getCurrentTenant();
        if ($tenant) {
            $query->where('tenant_id', $tenant->id);
        }
        return $query;
    }
}
