<?php namespace Aero\Sites\Traits;

use Aero\Sites\Models\Tenant;
use BackendAuth;
use System\Classes\SiteManager;

trait ResolvesCurrentTenant
{
    protected ?Tenant $currentTenant = null;

    protected function getCurrentTenant(): ?Tenant
    {
        if ($this->currentTenant) return $this->currentTenant;

        // Use OctoberCMS native SiteManager — single source of truth for backend site context
        $site = SiteManager::instance()->getEditSite();

        if ($site?->id) {
            $this->currentTenant = Tenant::where('site_id', $site->id)->first();
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
