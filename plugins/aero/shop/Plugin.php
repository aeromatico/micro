<?php namespace Aero\Shop;

use Backend;
use Event;
use System\Classes\PluginBase;

class Plugin extends PluginBase
{
    public $require = ['Aero.Sites'];

    public function pluginDetails(): array
    {
        return [
            'name'        => 'Shop',
            'description' => 'Tienda en línea por tenant — catálogo con variantes, inventario, monedas y pedidos',
            'author'      => 'Aero',
            'icon'        => 'icon-shopping-cart',
            'homepage'    => 'https://panel.market.com.bo',
        ];
    }

    public function register(): void
    {
        $this->registerPaymentGatewayDrivers();
    }

    public function registerComponents(): array
    {
        return [
            \Aero\Shop\Components\ShopCatalog::class       => 'shopCatalog',
            \Aero\Shop\Components\ProductDetail::class     => 'shopProductDetail',
            \Aero\Shop\Components\Cart::class               => 'shopCart',
            \Aero\Shop\Components\Checkout::class           => 'shopCheckout',
            \Aero\Shop\Components\OrderConfirmation::class  => 'shopOrderConfirmation',
        ];
    }

    public function boot(): void
    {
        $this->bootTenantPurgeCleanup();
        $this->bootPayPaymentBridge();
        $this->bootChatbotsIntegration();
        $this->bootRestApi();
    }

    /**
     * API REST básica de la tienda (catálogo, métodos de pago y pedidos),
     * autenticada con las keys de Aero.Api y acotada a la tienda del dueño de
     * la key. Es dependencia blanda: sin Aero.Api no hay middleware `aero.api`,
     * así que las rutas ni se registran.
     */
    protected function bootRestApi(): void
    {
        if (!class_exists(\Aero\Api\Classes\ScopeRegistry::class)) {
            return;
        }

        $this->app['router']->group([], function () {
            require __DIR__ . '/routes.php';
        });

        Event::listen('aero.api.registerScopes', function () {
            return ['shop' => ['label' => 'Tienda', 'scopes' => \Aero\Shop\Classes\Api\Scopes::all()]];
        });

        if (!class_exists(\Aero\Api\Classes\EndpointRegistry::class)) {
            return;
        }

        Event::listen('aero.api.registerEndpoints', function () {
            $scopes = \Aero\Shop\Classes\Api\Scopes::class;

            return ['shop' => [
                'label' => 'Tienda',
                'endpoints' => [
                    [
                        'method' => 'GET', 'path' => '/api/v1/shop/products', 'scope' => $scopes::PRODUCTS_READ,
                        'summary' => 'Buscar productos del catálogo.',
                        'query' => [
                            ['name' => 'q', 'type' => 'string', 'help' => 'Texto libre sobre nombre/descripción.'],
                            ['name' => 'collection_id', 'type' => 'integer', 'help' => 'Filtra por colección.'],
                            ['name' => 'per_page', 'type' => 'integer', 'default' => 20, 'help' => 'Resultados por página.'],
                        ],
                    ],
                    [
                        'method' => 'GET', 'path' => '/api/v1/shop/products/{id}', 'scope' => $scopes::PRODUCTS_READ,
                        'summary' => 'Detalle de un producto.',
                        'path_params' => [['name' => 'id', 'type' => 'integer', 'required' => true, 'help' => 'ID del producto.']],
                    ],
                    [
                        'method' => 'GET', 'path' => '/api/v1/shop/payment-methods', 'scope' => $scopes::PRODUCTS_READ,
                        'summary' => 'Métodos de pago activos de la tienda.',
                    ],
                    [
                        'method' => 'GET', 'path' => '/api/v1/shop/orders', 'scope' => $scopes::ORDERS_READ,
                        'summary' => 'Lista de pedidos.',
                        'query' => [
                            ['name' => 'status', 'type' => 'string', 'help' => 'Ej: pending, paid, cancelled.'],
                            ['name' => 'phone', 'type' => 'string', 'help' => 'Teléfono del cliente.'],
                            ['name' => 'per_page', 'type' => 'integer', 'default' => 20, 'help' => 'Máx. 100.'],
                        ],
                    ],
                    [
                        'method' => 'GET', 'path' => '/api/v1/shop/orders/{ref}', 'scope' => $scopes::ORDERS_READ,
                        'summary' => 'Detalle de un pedido, por ID o número.',
                        'path_params' => [['name' => 'ref', 'type' => 'string', 'required' => true, 'help' => 'ID o número de pedido (ej. #1024).']],
                    ],
                    [
                        'method' => 'POST', 'path' => '/api/v1/shop/orders', 'scope' => $scopes::ORDERS_WRITE,
                        'summary' => 'Crea un pedido nuevo.',
                        'body_example' => "{\n    \"customer\": {\n        \"first_name\": \"Ana\",\n        \"phone\": \"70000000\"\n    },\n    \"items\": [\n        {\"product_id\": 1, \"quantity\": 2}\n    ],\n    \"notes\": \"Entregar en la tarde\"\n}",
                    ],
                    [
                        'method' => 'POST', 'path' => '/api/v1/shop/orders/{ref}/cancel', 'scope' => $scopes::ORDERS_WRITE,
                        'summary' => 'Cancela un pedido.',
                        'path_params' => [['name' => 'ref', 'type' => 'string', 'required' => true, 'help' => 'ID o número de pedido.']],
                        'body_example' => "{\n    \"reason\": \"El cliente lo pidió\"\n}",
                    ],
                ],
            ]];
        });
    }

    /**
     * Aero.Chatbots no conoce el catálogo de la tienda — pregunta por evento
     * qué "AI tools" hay disponibles para el modo Súper IA. Dependencia
     * blanda: si Aero.Chatbots no está instalado, este listener simplemente
     * nunca se dispara.
     */
    protected function bootChatbotsIntegration(): void
    {
        Event::listen('aero.chatbots.registerAiTools', function () {
            return [
                'list_products' => [
                    'description' => 'Lista o busca productos activos del catálogo de la tienda, con precio y disponibilidad.',
                    'category'    => 'shop',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'query' => [
                                'type'        => 'string',
                                'description' => 'Texto para buscar por nombre o descripción. Vacío para listar los primeros productos del catálogo.',
                            ],
                        ],
                    ],
                    'handler' => [\Aero\Shop\Classes\Ai\ChatbotTools::class, 'listProducts'],
                ],
            ];
        });
    }

    protected function registerPaymentGatewayDrivers(): void
    {
        $this->app->singleton(
            \Aero\Shop\Classes\PaymentGatewayManager::class,
            fn() => new \Aero\Shop\Classes\PaymentGatewayManager()
        );

        Event::listen('aero.shop.registerPaymentGateways', function ($manager) {
            $manager->register('manual', \Aero\Shop\Classes\PaymentGateways\ManualPaymentGateway::class);

            if (class_exists(\Aero\Pay\Classes\QrIssuer::class)) {
                $manager->register('pagos_qr', \Aero\Shop\Classes\PaymentGateways\PagosQrGateway::class);
            }
        });

        $manager = $this->app->make(\Aero\Shop\Classes\PaymentGatewayManager::class);
        Event::fire('aero.shop.registerPaymentGateways', [$manager]);
    }

    /**
     * Cuando aero/qrbo confirma el pago de un QR (webhook del banco o
     * reconciliación cada 5 min), busca el pedido con ese `payment_reference`
     * (guardado por PagosQrGateway::issueForOrder()) y lo marca "paid" sin
     * intervención del vendedor. No hace nada si aero/qrbo no está instalado.
     */
    protected function bootPayPaymentBridge(): void
    {
        if (!class_exists(\Aero\Pay\Models\QrCode::class)) {
            return;
        }

        Event::listen('aero.pay.paymentReceived', function ($payment, $qrCode) {
            $order = \Aero\Shop\Models\Order::where('tenant_id', $qrCode->tenant_id)
                ->where('payment_reference', $qrCode->internal_reference)
                ->where('status', 'awaiting_payment')
                ->first();

            if (!$order) {
                return;
            }

            $order->status = 'paid';
            $order->paid_at = now();
            $order->save();

            $order->status_history()->create([
                'from_status' => 'awaiting_payment',
                'to_status'   => 'paid',
            ]);

            \Aero\Shop\Classes\OrderNotifier::fire($order, 'paid');
        });
    }

    /**
     * Menú superior propio "Tienda" (independiente de "Sitio Web" de
     * Aero.Sites). "Configuración de tienda" siempre visible (es donde está
     * el switch "Tienda activada"); el resto de los ítems solo se muestran
     * si la tienda está activada para el tenant actual.
     */
    public function registerNavigation(): array
    {
        $sideMenu = [
            'shop-configuracion' => [
                'label'       => 'aero.shop::lang.menu.settings',
                'icon'        => 'icon-cog',
                'url'         => Backend::url('aero/shop/shopsettings'),
                'permissions' => ['aero.shop.manage_settings'],
            ],
        ];

        if ($this->isShopEnabledForCurrentTenant()) {
            $sideMenu += [
                'shop-productos' => [
                    'label'       => 'aero.shop::lang.menu.products',
                    'icon'        => 'icon-cubes',
                    'url'         => Backend::url('aero/shop/products'),
                    'permissions' => ['aero.shop.manage_products'],
                ],
                'shop-colecciones' => [
                    'label'       => 'aero.shop::lang.menu.collections',
                    'icon'        => 'icon-folder-open',
                    'url'         => Backend::url('aero/shop/collections'),
                    'permissions' => ['aero.shop.manage_collections'],
                ],
                'shop-pedidos' => [
                    'label'       => 'aero.shop::lang.menu.orders',
                    'icon'        => 'icon-shopping-bag',
                    'url'         => Backend::url('aero/shop/orders'),
                    'permissions' => ['aero.shop.manage_orders'],
                ],
                'shop-clientes' => [
                    'label'       => 'aero.shop::lang.menu.customers',
                    'icon'        => 'icon-users',
                    'url'         => Backend::url('aero/shop/customers'),
                    'permissions' => ['aero.shop.manage_orders'],
                ],
                'shop-metodos-pago' => [
                    'label'       => 'aero.shop::lang.menu.payment_gateways',
                    'icon'        => 'icon-credit-card',
                    'url'         => Backend::url('aero/shop/paymentgateways'),
                    'permissions' => ['aero.shop.manage_payment_gateways'],
                ],
            ];

            if ($this->isInventoryEnabledForCurrentTenant()) {
                $sideMenu['shop-inventario'] = [
                    'label'       => 'aero.shop::lang.menu.inventory',
                    'icon'        => 'icon-archive',
                    'url'         => Backend::url('aero/shop/stockmovements'),
                    'permissions' => ['aero.shop.manage_inventory'],
                ];
            }
        }

        return [
            'tienda' => [
                'label'       => 'aero.shop::lang.menu.top',
                'url'         => Backend::url('aero/shop/shopsettings'),
                'icon'        => 'icon-shopping-cart',
                'permissions' => [
                    'aero.shop.manage_products', 'aero.shop.manage_collections', 'aero.shop.manage_orders',
                    'aero.shop.manage_inventory', 'aero.shop.manage_payment_gateways', 'aero.shop.manage_settings',
                ],
                'order'       => 150,
                'sideMenu'    => $sideMenu,
            ],
        ];
    }

    /**
     * Resuelve el tenant del usuario de backend actual (mismo criterio que
     * Aero\Sites\Traits\ResolvesCurrentTenant, replicado aquí porque Plugin.php
     * no es un controlador) y devuelve si su tienda está activada.
     */
    protected function isShopEnabledForCurrentTenant(): bool
    {
        $tenantId = $this->resolveCurrentBackendTenantId();
        if (!$tenantId) {
            return false;
        }

        return (bool) \Aero\Shop\Models\ShopSettings::where('tenant_id', $tenantId)->value('is_enabled');
    }

    /**
     * El switch "Usar sistema de inventario" en Configuración de tienda apaga
     * por completo la validación/reserva de stock (ver InventoryService) — si
     * el tenant no lo usa, el menú "Inventario" tampoco tiene sentido mostrarlo.
     */
    protected function isInventoryEnabledForCurrentTenant(): bool
    {
        $tenantId = $this->resolveCurrentBackendTenantId();
        if (!$tenantId) {
            return false;
        }

        return \Aero\Shop\Models\ShopSettings::inventoryEnabledForTenant($tenantId);
    }

    protected function resolveCurrentBackendTenantId(): ?int
    {
        $user = \BackendAuth::getUser();
        if (!$user) {
            return null;
        }

        $tenantId = null;

        $site = \System\Classes\SiteManager::instance()->getEditSite();
        if ($site?->id) {
            $tenantId = \Aero\Sites\Models\Tenant::where('site_id', $site->id)->value('id');
        }

        if (!$tenantId) {
            $tenantId = \Aero\Sites\Models\Tenant::where('backend_user_id', $user->id)->value('id');
        }

        if (!$tenantId) {
            $tenantId = \Aero\Sites\Models\TenantUser::where('user_id', $user->id)->value('tenant_id');
        }

        return $tenantId ?: null;
    }

    /**
     * Al purgar un tenant (Aero\Sites\Models\Tenant::purge()), borra en cascada
     * todos los datos de shop asociados, para no dejar huérfanos.
     */
    protected function bootTenantPurgeCleanup(): void
    {
        Event::listen('aero.sites.tenant.purging', function ($tenant) {
            $tenantId = $tenant->id;

            \Aero\Shop\Models\OrderStatusHistory::whereIn('order_id', function ($q) use ($tenantId) {
                $q->select('id')->from('aero_shop_orders')->where('tenant_id', $tenantId);
            })->delete();

            \Aero\Shop\Models\StockMovement::where('tenant_id', $tenantId)->delete();
            \Aero\Shop\Models\OrderItem::where('tenant_id', $tenantId)->delete();
            \Aero\Shop\Models\Order::where('tenant_id', $tenantId)->delete();
            \Aero\Shop\Models\Address::where('tenant_id', $tenantId)->delete();
            \Aero\Shop\Models\Customer::where('tenant_id', $tenantId)->delete();
            \Aero\Shop\Models\PaymentGateway::where('tenant_id', $tenantId)->delete();

            \Aero\Shop\Models\ProductVariant::where('tenant_id', $tenantId)->each(function ($variant) {
                $variant->image()->delete();
                $variant->digital_file()->delete();
                $variant->forceDelete();
            });
            \Aero\Shop\Models\ProductOptionValue::where('tenant_id', $tenantId)->delete();
            \Aero\Shop\Models\ProductOption::where('tenant_id', $tenantId)->delete();
            \Aero\Shop\Models\Product::withTrashed()->where('tenant_id', $tenantId)->each(function ($product) {
                $product->images()->delete();
                $product->digital_file()->delete();
                $product->forceDelete();
            });
            \Aero\Shop\Models\Collection::withTrashed()->where('tenant_id', $tenantId)->each(function ($collection) {
                $collection->image()->delete();
                $collection->forceDelete();
            });

            \Aero\Shop\Models\TenantCurrency::where('tenant_id', $tenantId)->delete();
            \Aero\Shop\Models\ShopSettings::where('tenant_id', $tenantId)->delete();
        });
    }

    public function registerPermissions(): array
    {
        return [
            'aero.shop.manage_products' => [
                'tab'   => 'Shop',
                'label' => 'aero.shop::lang.permissions.manage_products',
            ],
            'aero.shop.manage_collections' => [
                'tab'   => 'Shop',
                'label' => 'aero.shop::lang.permissions.manage_collections',
            ],
            'aero.shop.manage_orders' => [
                'tab'   => 'Shop',
                'label' => 'aero.shop::lang.permissions.manage_orders',
            ],
            'aero.shop.manage_inventory' => [
                'tab'   => 'Shop',
                'label' => 'aero.shop::lang.permissions.manage_inventory',
            ],
            'aero.shop.manage_payment_gateways' => [
                'tab'   => 'Shop',
                'label' => 'aero.shop::lang.permissions.manage_payment_gateways',
            ],
            'aero.shop.manage_settings' => [
                'tab'   => 'Shop',
                'label' => 'aero.shop::lang.permissions.manage_settings',
            ],
        ];
    }
}
