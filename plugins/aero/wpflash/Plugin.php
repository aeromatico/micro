<?php namespace Aero\WpFlash;

use Backend;
use Event;
use System\Classes\PluginBase;
use Aero\WpFlash\Models\SiteInstance;

/**
 * "WordPress Flash": motor de sitio alternativo para tenants acostumbrados a
 * WordPress/WooCommerce. Al activarlo, el tenant recibe un childsite en
 * nuestro WordPress Multisite (creado automáticamente, con usuario/contraseña
 * de admin) y su subdominio/dominio se apunta a ese childsite en vez de
 * nuestro CMS (ver Classes\DomainRouter). WooCommerce manda siempre: el
 * catálogo (productos/clientes) se sincroniza en un solo sentido hacia
 * Aero.Shop, en tiempo real vía webhooks de WooCommerce y con reconciliación
 * periódica de respaldo.
 *
 * Requiere Aero.Sites (dueño del Tenant/Dominio que se enrutan) y
 * Aero.Connector (dueño del abstracto driver/tipo/log que este plugin
 * registra para hablar con WooCommerce). WordPress vive en este mismo
 * servidor: el provisioning y el enrutamiento del dominio corren por WP-CLI
 * y nginx (ver Classes\WpCli, Classes\DomainRouter), no por HTTP. Aero.Shop
 * es opcional (class_exists): sin él, WP Flash sigue sirviendo para el
 * enrutamiento de dominio, solo se omite el sync de catálogo.
 */
class Plugin extends PluginBase
{
    public $require = ['Aero.Sites', 'Aero.Connector'];

    public function pluginDetails(): array
    {
        return [
            'name'        => 'aero.wpflash::lang.plugin.name',
            'description' => 'aero.wpflash::lang.plugin.description',
            'author'      => 'Aero',
            'icon'        => 'icon-exchange',
            'homepage'    => 'https://panel.market.com.bo',
        ];
    }

    public function register(): void
    {
        $this->registerConsoleCommand('wpflash:sync', \Aero\WpFlash\Console\SyncCommand::class);
        $this->registerConsoleCommand('wpflash:nginx-sync', \Aero\WpFlash\Console\NginxSyncCommand::class);
    }

    public function boot(): void
    {
        $this->bootTenantIntegration();
        $this->registerConnectorTypes();
        $this->registerWebhookListeners();
    }

    public function registerSchedule($schedule): void
    {
        // Sin esto los productos/clientes que un webhook perdido (WP caído,
        // red, reinicio) no trajo quedarían desincronizados indefinidamente
        // — aero/connector no trae cron propio, cada plugin agrega el suyo.
        $schedule->command('wpflash:sync')->everyFifteenMinutes()->withoutOverlapping();

        // Red de seguridad: regenera storage/app/wpflash/tenants.conf aunque
        // DomainRouter ya lo haga en cada activar/desactivar (ver deploy/apply-nginx.sh).
        $schedule->command('wpflash:nginx-sync')->everyFiveMinutes()->withoutOverlapping();
    }

    /**
     * `Tenant` (Aero.Sites) no conoce este plugin — se le agrega la relación
     * al revés, igual que Aero.Sites hace con Aero.Hello/Aero.Api.
     */
    protected function bootTenantIntegration(): void
    {
        \Aero\Sites\Models\Tenant::extend(function ($model) {
            $model->hasOne['wpFlashSite'] = [SiteInstance::class, 'key' => 'tenant_id'];
        });
    }

    /**
     * Suma `woocommerce` al catálogo de Aero.Connector: un Connector por
     * tenant, contra su childsite (auth Basic con usuario + application
     * password de WordPress — ver Classes\Provisioner). El childsite en sí y
     * el apuntado del dominio del tenant NO pasan por un Connector: se hacen
     * por WP-CLI/nginx (Classes\WpCli, Classes\DomainRouter), porque
     * WordPress y esta plataforma viven en el mismo servidor.
     */
    protected function registerConnectorTypes(): void
    {
        Event::listen('aero.connector.registerTypes', function () {
            return [
                'woocommerce' => [
                    'label'         => trans('aero.wpflash::lang.types.woocommerce'),
                    'category'      => 'ecommerce',
                    'driver'        => \Aero\WpFlash\Drivers\WooCommerceDriver::class,
                    'provider_hint' => 'woocommerce',
                ],
            ];
        });
    }

    /**
     * Entrada en tiempo real: WooCommerce entrega sus webhooks de
     * producto/cliente al endpoint compartido `/connector/webhooks/wpflash-*`
     * (uno por tópico, no por tenant — WebhookEndpoint.slug es único). El
     * tenant se resuelve adentro del listener por el sitio de origen del
     * payload, no por el endpoint (todos los tenants comparten la misma URL).
     */
    protected function registerWebhookListeners(): void
    {
        Event::listen('aero.connector.webhook.wpflash_product', function ($endpoint, $payload, $request) {
            \Aero\WpFlash\Classes\EventListeners::handleProductWebhook($payload, $request);
        });

        Event::listen('aero.connector.webhook.wpflash_customer', function ($endpoint, $payload, $request) {
            \Aero\WpFlash\Classes\EventListeners::handleCustomerWebhook($payload, $request);
        });
    }

    public function registerPermissions(): array
    {
        return [
            'aero.wpflash.manage_sites' => [
                'tab'   => 'aero.wpflash::lang.plugin.name',
                'label' => 'aero.wpflash::lang.permissions.manage_sites',
            ],
        ];
    }

    public function registerNavigation(): array
    {
        return [
            'wpflash' => [
                'label'       => 'aero.wpflash::lang.menu.wpflash',
                'url'         => Backend::url('aero/wpflash/siteengine'),
                'icon'        => 'icon-exchange',
                'permissions' => ['aero.wpflash.manage_sites'],
                'order'       => 590,
                'sideMenu'    => [
                    'siteengine' => [
                        'label'       => 'aero.wpflash::lang.menu.wpflash',
                        'icon'        => 'icon-exchange',
                        'url'         => Backend::url('aero/wpflash/siteengine'),
                        'permissions' => ['aero.wpflash.manage_sites'],
                    ],
                ],
            ],
        ];
    }

    public function registerSettings(): array
    {
        return [
            'settings' => [
                'label'       => 'aero.wpflash::lang.plugin.name',
                'description' => 'aero.wpflash::lang.settings.description',
                'category'    => 'Sistema',
                'icon'        => 'icon-exchange',
                'class'       => \Aero\WpFlash\Models\Settings::class,
                'order'       => 590,
                'permissions' => ['aero.wpflash.manage_sites'],
            ],
        ];
    }
}
