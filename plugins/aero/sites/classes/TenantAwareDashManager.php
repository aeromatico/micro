<?php namespace Aero\Sites\Classes;

use Dashboard\Classes\DashManager;
use Dashboard\Classes\SystemReportDataSource;

/**
 * Filtra del panel de indicadores del dashboard las fuentes de datos que
 * describen infraestructura de TODA la instalación (versión, logs, salud del
 * servidor) y no pueden acotarse a un sitio/tenant individual. Un tenant_admin
 * no debería poder agregar ni ver "System Build", "System Issues", etc.
 *
 * "Traffic Information" y "Website Status" sí se mantienen: ya filtran por
 * Site::getEditSite(), que Aero\Sites\Plugin::bootSiteContext() resuelve al
 * sitio del tenant actual.
 */
class TenantAwareDashManager extends DashManager
{
    protected const HIDDEN_FOR_TENANTS = [
        SystemReportDataSource::class,
    ];

    public function listDataSourceClasses(): array
    {
        return array_diff_key(parent::listDataSourceClasses(), array_flip(self::HIDDEN_FOR_TENANTS));
    }
}
