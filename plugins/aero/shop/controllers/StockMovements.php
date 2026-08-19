<?php namespace Aero\Shop\Controllers;

use Aero\Shop\Classes\InventoryService;
use Aero\Shop\Models\Product;
use Aero\Shop\Models\ProductVariant;
use Aero\Sites\Traits\ResolvesCurrentTenant;
use Backend\Classes\Controller;
use BackendAuth;
use BackendMenu;
use Flash;

class StockMovements extends Controller
{
    use ResolvesCurrentTenant;

    public $implement = [
        \Backend\Behaviors\ListController::class,
    ];

    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = ['aero.shop.manage_inventory'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Shop', 'tienda', 'shop-inventario');
    }

    public function listExtendQuery($query): void
    {
        $this->scopeQueryToTenant($query);
    }

    public function index()
    {
        $this->pageTitle = 'Movimientos de inventario';
        $tenantId = $this->getCurrentTenantId() ?: 0;

        $this->vars['products'] = Product::forTenant($tenantId)->orderBy('name')->get();

        $this->asExtension('ListController')->index();
    }

    public function onLoadAdjustmentForm()
    {
        $tenantId = $this->getCurrentTenantId() ?: 0;
        $this->vars['products'] = Product::forTenant($tenantId)->orderBy('name')->get();

        return $this->makePartial('adjustment_form');
    }

    public function onLoadVariants()
    {
        $productId = (int) post('product_id');
        $tenantId  = $this->getCurrentTenantId() ?: 0;

        $this->vars['variants'] = ProductVariant::forTenant($tenantId)->where('product_id', $productId)->get();

        return $this->makePartial('variant_options');
    }

    public function onSaveAdjustment()
    {
        $tenantId  = $this->getCurrentTenantId() ?: 0;
        $productId = (int) post('product_id');
        $variantId = post('variant_id') ?: null;
        $delta     = (int) post('quantity_delta');
        $note      = post('note');

        $product = Product::forTenant($tenantId)->findOrFail($productId);
        $item = $variantId
            ? ProductVariant::forTenant($tenantId)->where('product_id', $productId)->findOrFail($variantId)
            : $product;

        (new InventoryService)->manualAdjustment($item, $delta, $note, BackendAuth::getUser()->id);

        Flash::success('Ajuste de inventario registrado.');

        return $this->listRefresh();
    }
}
