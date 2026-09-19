<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enable Multisite
    |--------------------------------------------------------------------------
    |
    | Allows the creation of multiple site definitions in the same installation.
    | Disabling this will lock any existing site definitions.
    |
    */

    'enabled' => true,

    /*
    |--------------------------------------------------------------------------
    | Multisite Features
    |--------------------------------------------------------------------------
    |
    | Use multisite for the features defined below. Be sure to clear the application
    | cache after modifying these settings.
    |
    |  - system_plugin_sites - Plugins can be enabled/disabled per site
    |  - system_plugin_site_groups - Plugins can be enabled/disabled per site group
    |  - system_asset_combiner - Asset combiner cache keys are unique to the site
    |  - cms_maintenance_setting - Maintenance Mode Settings are unique for each site
    |  - backend_mail_setting - Mail Settings are unique for each site
    |
    | There are also some known vendor implementations.
    |
    |  - rainlab_googleanalytics_setting - Google Analytics for each site
    |  - responsiv_campaign_message - Mailing list campaigns for each site
    |
    */

    'features' => [
        'system_plugin_sites' => true,
        'system_plugin_site_groups' => false,
        'system_asset_combiner' => true,
        'cms_maintenance_setting' => true,
        'backend_mail_setting' => false,
        // Habilitado: cada Tenant de Aero.Sites tiene su propio SiteDefinition
        // (site_id), y Aero\Sites\Plugin::bootSiteContext() ya resuelve
        // Site::getEditSite() al sitio del tenant actual. Sin esta bandera,
        // CmsReportDataSource (indicador "Traffic Information") no filtra por
        // site_id y todos los tenant_admin ven el tráfico de toda la plataforma.
        'dashboard_traffic_statistics' => true,

        // Vendor
        'rainlab_googleanalytics_setting' => false,
        'responsiv_campaign_message' => false,
    ],

];
