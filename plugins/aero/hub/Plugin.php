<?php namespace Aero\Hub;

use Backend;
use Event;
use Route;
use System\Classes\PluginBase;

/**
 * Proxy de YepAPI (https://api.yepapi.com): 155 endpoints de terceros
 * (Modelos IA + APIs de datos) reexpuestos bajo /hub/v1/... con el mismo path
 * exacto, para heredar su documentación pública cambiando solo el dominio.
 *
 * Integración por dependencia blanda (class_exists + Event::listen), nunca un
 * `use` directo fuera del guard: Aero.Connector ejecuta la llamada saliente
 * (driver propio "yepapi"), Aero.Api autentica al tenant y valida scopes, y
 * Aero.Credits cobra el consumo. El plugin sigue funcionando (sin proxyear
 * nada útil) si alguno de los tres falta, salvo Aero.Connector que es
 * imprescindible para ejecutar la llamada saliente.
 */
class Plugin extends PluginBase
{
    public $require = ['Aero.Connector'];

    public function pluginDetails(): array
    {
        return [
            'name'        => 'aero.hub::lang.plugin.name',
            'description' => 'aero.hub::lang.plugin.description',
            'author'      => 'Aero',
            'icon'        => 'icon-cloud',
            'homepage'    => 'https://panel.market.com.bo',
        ];
    }

    public function register(): void
    {
        $this->registerConsoleCommand('hub:sync-catalog', \Aero\Hub\Console\SyncCatalog::class);
    }

    public function boot(): void
    {
        $this->registerConnectorType();
        $this->registerApiIntegration();
        $this->registerRoutes();
    }

    /**
     * Un solo driver reutiliza un único Connector (credencial x-api-key
     * compartida) para las 155 rutas: el path/método reales vienen en el
     * payload (mismo patrón que TelegramDriver enrutando por nombre de
     * método), en vez de necesitar 155 filas de Connector.
     */
    protected function registerConnectorType(): void
    {
        Event::listen('aero.connector.registerTypes', function () {
            return [
                'yepapi' => [
                    'label'            => trans('aero.hub::lang.plugin.connector_type'),
                    'category'         => 'http',
                    'driver'           => \Aero\Hub\Classes\Connector\YepApiDriver::class,
                    'provider_hint'    => 'yepapi',
                    'default_base_url' => 'https://api.yepapi.com',
                ],
            ];
        });
    }

    /**
     * Con Aero.Api no instalado, el proxy sigue montado pero
     * ProxyController::handle() rechaza toda petición (no hay forma de
     * autenticar), así que no hace falta un guard adicional acá dentro.
     */
    protected function registerApiIntegration(): void
    {
        if (!class_exists(\Aero\Api\Classes\ScopeRegistry::class)) {
            return;
        }

        Event::listen('aero.api.registerScopes', function () {
            return \Aero\Hub\Classes\CatalogSync::scopeGroups();
        });

        Event::listen('aero.api.registerEndpoints', function () {
            return \Aero\Hub\Classes\CatalogSync::endpointGroups();
        });
    }

    protected function registerRoutes(): void
    {
        $this->app['router']->group([], function () {
            require __DIR__ . '/routes.php';
        });
    }

    public function registerPermissions(): array
    {
        return [
            'aero.hub.manage_catalog' => [
                'tab'   => 'aero.hub::lang.plugin.name',
                'label' => 'aero.hub::lang.permissions.manage_catalog',
            ],
        ];
    }

    public function registerNavigation(): array
    {
        return [
            'hub' => [
                'label'       => 'aero.hub::lang.menu.hub',
                'url'         => Backend::url('aero/hub/hubendpoints'),
                'icon'        => 'icon-cloud',
                'permissions' => ['aero.hub.manage_catalog'],
                'order'       => 580,
                'sideMenu'    => [
                    'ai_models' => [
                        'label'       => 'aero.hub::lang.menu.ai_models',
                        'icon'        => 'icon-magic',
                        'url'         => Backend::url('aero/hub/hubendpoints') . '?HubEndpoints-division=ai_models',
                        'permissions' => ['aero.hub.manage_catalog'],
                    ],
                    'apis' => [
                        'label'       => 'aero.hub::lang.menu.apis',
                        'icon'        => 'icon-exchange',
                        'url'         => Backend::url('aero/hub/hubendpoints') . '?HubEndpoints-division=apis',
                        'permissions' => ['aero.hub.manage_catalog'],
                    ],
                ],
            ],
        ];
    }
}
