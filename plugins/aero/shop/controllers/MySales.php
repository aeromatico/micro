<?php namespace Aero\Shop\Controllers;

use Aero\Shop\Classes\SellerBalance;
use Aero\Shop\Models\Order;
use Aero\Shop\Models\Seller;
use Aero\Shop\Models\ShopSettings;
use Aero\Sites\Traits\ResolvesCurrentTenant;
use Backend\Classes\Controller;
use BackendAuth;
use BackendMenu;

/** «Mis ventas»: el comerciante ve solo sus pedidos y su balance. Falla cerrado si no es comerciante activo. */
class MySales extends Controller
{
    use ResolvesCurrentTenant;

    public $requiredPermissions = ['aero.shop.sell'];

    public $pageTitle = 'Mis ventas';

    public $seller;
    public $balance = [];
    public $orders = [];
    public $from;
    public $to;

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Shop', 'tienda', 'shop-mis-ventas');
    }

    public function index()
    {
        $tenantId = $this->getCurrentTenantId();
        if (!$tenantId || !ShopSettings::isMarketplaceForTenant($tenantId)) {
            return \Response::make('El modo Marketplace no está activo en esta tienda.', 404);
        }

        $this->seller = Seller::forUser(BackendAuth::getUser(), $tenantId);
        if (!$this->seller) {
            return \Response::make('No estás registrado como comerciante de esta tienda.', 403);
        }

        $this->from = input('from') ?: null;
        $this->to = input('to') ?: null;
        $this->balance = SellerBalance::for($tenantId, $this->seller->id, $this->from, $this->to);
        $this->orders = Order::forTenant($tenantId)->where('seller_id', $this->seller->id)
            ->when($this->from, fn ($q) => $q->whereDate('created_at', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->whereDate('created_at', '<=', $this->to))
            ->with('customer')->latest()->limit(200)->get();
    }
}
