<?php namespace Aero\Pos;

use Backend;
use Event;
use System\Classes\PluginBase;

class Plugin extends PluginBase
{
    public $require = ['Aero.Shop', 'Aero.Sites'];

    public function pluginDetails(): array
    {
        return [
            'name'        => 'Punto de venta',
            'description' => 'POS rápido sobre la tienda: ventas de mostrador y mesas, cobros divididos, turnos de caja y tickets',
            'author'      => 'Aero',
            'icon'        => 'icon-calculator',
            'homepage'    => 'https://panel.market.com.bo',
        ];
    }

    public function boot(): void
    {
        $this->bootTenantPurgeCleanup();

        // La app de venta es una función PRO seleccionable por plan (Settings → Sites).
        Event::listen('aero.sites.registerProFeatures', function () {
            return ['aero/pos/app' => ['plugin' => 'Aero.Pos', 'label' => 'Punto de venta (app /pos)']];
        });
    }

    /** El menú del POS solo aparece cuando la tienda del tenant está activada. */
    public function registerNavigation(): array
    {
        $sideMenu = [];

        if ($this->isShopEnabledForCurrentTenant()) {
            $sideMenu = [
                'pos-ventas'    => ['label' => 'aero.pos::lang.menu.sales', 'icon' => 'icon-list-alt', 'url' => Backend::url('aero/pos/sales'), 'permissions' => ['aero.pos.reports']],
                'pos-turnos'    => ['label' => 'aero.pos::lang.menu.shifts', 'icon' => 'icon-clock', 'url' => Backend::url('aero/pos/shifts'), 'permissions' => ['aero.pos.use', 'aero.pos.manage_shifts']],
                'pos-mesas'     => ['label' => 'aero.pos::lang.menu.tables', 'icon' => 'icon-table', 'url' => Backend::url('aero/pos/tables'), 'permissions' => ['aero.pos.manage']],
                'pos-metodos'   => ['label' => 'aero.pos::lang.menu.payment_methods', 'icon' => 'icon-credit-card', 'url' => Backend::url('aero/pos/paymentmethods'), 'permissions' => ['aero.pos.manage']],
                'pos-terminales' => ['label' => 'aero.pos::lang.menu.terminals', 'icon' => 'icon-desktop', 'url' => Backend::url('aero/pos/terminals'), 'permissions' => ['aero.pos.manage']],
                'pos-cajeros'   => ['label' => 'aero.pos::lang.menu.cashiers', 'icon' => 'icon-users', 'url' => Backend::url('aero/pos/cashiers'), 'permissions' => ['aero.pos.manage']],
            ];
        }

        // Configuración siempre visible: ahí se entiende qué es el POS y se elige el perfil.
        $sideMenu['pos-configuracion'] = ['label' => 'aero.pos::lang.menu.settings', 'icon' => 'icon-cog', 'url' => Backend::url('aero/pos/settings'), 'permissions' => ['aero.pos.manage']];

        return [
            'pos' => [
                'label'       => 'aero.pos::lang.menu.top',
                'url'         => Backend::url('aero/pos/settings'),
                'icon'        => 'icon-calculator',
                'permissions' => ['aero.pos.use', 'aero.pos.manage', 'aero.pos.reports', 'aero.pos.manage_shifts'],
                'order'       => 155,
                'sideMenu'    => $sideMenu,
            ],
        ];
    }

    public function registerPermissions(): array
    {
        $perm = fn (string $key) => ['tab' => 'POS', 'label' => 'aero.pos::lang.permissions.' . $key];

        return [
            'aero.pos.use'           => $perm('use'),
            'aero.pos.discount'      => $perm('discount'),
            'aero.pos.void'          => $perm('void'),
            'aero.pos.manage_shifts' => $perm('manage_shifts'),
            'aero.pos.reports'       => $perm('reports'),
            'aero.pos.manage'        => $perm('manage'),
        ];
    }

    protected function isShopEnabledForCurrentTenant(): bool
    {
        $user = \BackendAuth::getUser();
        if (!$user) {
            return false;
        }

        $tenant = null;
        $site = \System\Classes\SiteManager::instance()->getEditSite();
        if ($site?->id) {
            $tenant = \Aero\Sites\Models\Tenant::where('site_id', $site->id)->first();
        }
        $tenant ??= \Aero\Sites\Models\Tenant::resolveForBackendUser($user);

        return $tenant && \Aero\Shop\Models\ShopSettings::where('tenant_id', $tenant->id)->value('is_enabled');
    }

    protected function bootTenantPurgeCleanup(): void
    {
        Event::listen('aero.sites.tenant.purging', function ($tenant) {
            $id = $tenant->id;
            foreach (['payments', 'sales', 'cash_movements', 'shifts', 'device_tokens', 'cashiers', 'tables', 'payment_methods', 'terminals', 'settings'] as $t) {
                \Db::table('aero_pos_' . $t)->where('tenant_id', $id)->delete();
            }
        });
    }
}
