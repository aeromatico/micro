<?php namespace Aero\Shop\Controllers;

use Aero\Shop\Classes\SellerBalance;
use Aero\Shop\Models\Order;
use Aero\Shop\Models\Seller;
use Aero\Shop\Models\ShopSettings;
use Aero\Sites\Traits\ResolvesCurrentTenant;
use Backend\Classes\Controller;
use BackendAuth;
use BackendMenu;

/**
 * Comerciantes del modo Marketplace. Solo el admin del tenant (dueño, admin del
 * equipo o superusuario): todos los miembros comparten el rol tenant_admin,
 * así que el acceso se decide aquí y falla cerrado.
 */
class Sellers extends Controller
{
    use ResolvesCurrentTenant;

    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = ['aero.shop.manage_sellers'];

    public $pageTitle = 'Comerciantes';

    public $balance = [];
    public $seller;
    public $orders = [];
    public $from;
    public $to;

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Shop', 'tienda', 'shop-vendedores');
    }

    /** Falla cerrado: sin tenant, sin modo marketplace o sin ser admin del tenant → 403/404. */
    public function run($action = null, $params = [])
    {
        if (!BackendAuth::check()) {
            return parent::run($action, $params); // el backend redirige al login
        }

        $tenantId = $this->getCurrentTenantId();
        if (!$tenantId || !ShopSettings::isMarketplaceForTenant($tenantId)) {
            return \Response::make('El modo Marketplace no está activo en esta tienda.', 404);
        }
        if (!Seller::seesEverything(BackendAuth::getUser(), $tenantId)) {
            return \Response::make('Solo el administrador de la tienda puede gestionar comerciantes.', 403);
        }

        return parent::run($action, $params);
    }

    public function listExtendQuery($query): void
    {
        $this->scopeQueryToTenant($query);
    }

    public function formExtendQuery($query): void
    {
        $this->scopeQueryToTenant($query);
    }

    public function formExtendModel($model): void
    {
        if (!$model->exists) {
            $model->tenant_id = $this->getCurrentTenantId();
            $model->is_active = true;
        }
    }

    public function formBeforeSave($model): void
    {
        // La cuenta de cobro debe ser del propio tenant (nunca de otro).
        if ($model->bank_account_id && !array_key_exists($model->bank_account_id, $model->getBankAccountIdOptions())) {
            throw new \ApplicationException('La cuenta de cobro elegida no pertenece a tu tienda.');
        }
        if (!$model->display_name && $model->user) {
            $model->display_name = trim($model->user->first_name . ' ' . $model->user->last_name) ?: $model->user->login;
        }
    }

    /** Detalle de un comerciante: balance y pedidos, con filtro de fechas. */
    public function detail($id = null)
    {
        $tenantId = $this->getCurrentTenantId();
        $this->seller = Seller::forTenant($tenantId)->findOrFail((int) $id);
        $this->pageTitle = $this->seller->display_name;
        $this->from = input('from') ?: null;
        $this->to = input('to') ?: null;

        $this->balance = SellerBalance::for($tenantId, $this->seller->id, $this->from, $this->to);
        $this->orders = Order::forTenant($tenantId)->where('seller_id', $this->seller->id)
            ->when($this->from, fn ($q) => $q->whereDate('created_at', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->whereDate('created_at', '<=', $this->to))
            ->with('customer')->latest()->limit(200)->get();
    }
}
