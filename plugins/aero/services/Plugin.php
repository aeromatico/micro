<?php namespace Aero\Services;

use Backend;
use Illuminate\Support\Facades\Cache;
use System\Classes\PluginBase;

/**
 * Catálogo de servicios de tecnología (ecommerce y marketing). Genérico,
 * pero cada servicio puede ligarse a uno o varios plugins de la plataforma.
 */
class Plugin extends PluginBase
{
    public function boot(): void
    {
        // El menú público (GET /api/v1/services/menu) se cachea: se invalida al editar el catálogo.
        $forget = fn () => Cache::forget(\Aero\Services\Http\Controllers\MenuController::CACHE_KEY);

        foreach ([\Aero\Services\Models\Service::class, \Aero\Services\Models\Category::class] as $model) {
            $model::saved($forget);
            $model::deleted($forget);
        }
    }

    public function pluginDetails(): array
    {
        return [
            'name'        => 'aero.services::lang.plugin.name',
            'description' => 'aero.services::lang.plugin.description',
            'author'      => 'Aero',
            'icon'        => 'icon-briefcase',
        ];
    }

    public function register(): void
    {
        $this->registerConsoleCommand('services.build-offer', \Aero\Services\Console\BuildOffer::class);
        $this->registerConsoleCommand('services.offer-proposal', \Aero\Services\Console\OfferProposal::class);
        $this->registerConsoleCommand('services.offer-apply', \Aero\Services\Console\OfferApply::class);
        $this->registerConsoleCommand('services.link-docs', \Aero\Services\Console\LinkDocs::class);
    }

    public function registerComponents(): array
    {
        return [\Aero\Services\Components\Services::class => 'services'];
    }

    public function registerPermissions(): array
    {
        return [
            'aero.services.manage' => [
                'tab'   => 'aero.services::lang.plugin.name',
                'label' => 'aero.services::lang.permissions.manage',
            ],
        ];
    }

    public function registerNavigation(): array
    {
        // El panel del tenant (App Store, comprar servicios) NO vive acá: es un
        // ícono del menú inferior (ver Aero.Credits\Plugin::bootNavbarWidget()),
        // igual que Wallet. Un ítem de sideMenu sin permisos lo haría "administrable
        // por plan" (Aero\Sites\Classes\ProFeatures::manageablePlugins()) y algún
        // plan podría terminar bloqueándolo por accidente.
        return [
            'services' => [
                'label'       => 'aero.services::lang.menu.services',
                'url'         => Backend::url('aero/services/services'),
                'icon'        => 'icon-briefcase',
                'permissions' => ['aero.services.manage'],
                'order'       => 565,
                'sideMenu'    => [
                    'services' => [
                        'label'       => 'aero.services::lang.menu.services',
                        'icon'        => 'icon-briefcase',
                        'url'         => Backend::url('aero/services/services'),
                        'permissions' => ['aero.services.manage'],
                    ],
                    'categories' => [
                        'label'       => 'aero.services::lang.menu.categories',
                        'icon'        => 'icon-folder-open-o',
                        'url'         => Backend::url('aero/services/categories'),
                        'permissions' => ['aero.services.manage'],
                    ],
                    'collections' => [
                        'label'       => 'aero.services::lang.menu.collections',
                        'icon'        => 'icon-th-large',
                        'url'         => Backend::url('aero/services/collections'),
                        'permissions' => ['aero.services.manage'],
                    ],
                    'purchases' => [
                        'label'       => 'aero.services::lang.menu.purchases',
                        'icon'        => 'icon-shopping-basket',
                        'url'         => Backend::url('aero/services/purchases'),
                        'permissions' => ['aero.services.manage'],
                    ],
                ],
            ],
        ];
    }
}
