<?php namespace Aero\Sites\Classes;

use BackendAuth;
use System\Classes\SiteManager;

/**
 * Oculta el selector rápido de sitios (junto al gravatar, en el topbar) para
 * cualquier backend user que no sea superadmin. `hasMultiEditSite()` es lo
 * único que decide si Backend\Widgets\SiteSwitcher dibuja el dropdown
 * (ver modules/backend/widgets/siteswitcher/partials/_siteswitcher.php), y
 * cuenta sitios con SiteDefinition::matchesRole(), que solo filtra por rol
 * restringido — no por tenant. En este SaaS cada tenant_admin tiene un único
 * sitio propio (ya resuelto vía Aero\Sites\Plugin::bootSiteContext()), así
 * que no necesita ni debería poder ver el resto en el selector.
 */
class TenantAwareSiteManager extends SiteManager
{
    public function hasMultiEditSite(): bool
    {
        $user = BackendAuth::getUser();
        if ($user && !$user->is_superuser) {
            return false;
        }

        return parent::hasMultiEditSite();
    }
}
